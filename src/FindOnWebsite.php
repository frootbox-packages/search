<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\PageOutline;
use Frootbox\Core\Search\SearchIndex;
use Frootbox\Core\Search\SearchIndexer;
use Frootbox\Core\Search\SearchRanking;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\View\Delivery;

/**
 * The search on the website (decision 0228): what is published in one
 * language, ranked like the global search. Left out is what has no
 * address in that language or only that of a fallback, and everything on
 * a page that is locked or left out of the search on the website, with
 * the pages and objects below it.
 *
 * @implements Query<WebsiteResults>
 */
#[AsQuery('frootbox/search/website.find', 'Finds published pages, articles, events and more on the website by words, as visitors see them, best hits first', public: true)]
final readonly class FindOnWebsite implements Query
{
    public function __construct(
        #[Length(1, 200)]
        public string $q,
        #[Length(2, 20)]
        #[Description('A language of the website, e.g. "de"; by default the one of the visitor')]
        public ?string $language = null,
        #[Range(1, 50)]
        public int $limit = 10,
        #[Range(0, 190)]
        public int $offset = 0,
    ) {}

    public function handle(SearchIndex $index, SearchIndexer $indexer, Urls $urls, PageOutline $outline, Languages $languages, Delivery $delivery): WebsiteResults
    {
        $language = null !== $this->language && $languages->has($this->language) ? $this->language : $delivery->language()->code;
        $words = SearchRanking::words($this->q, $language);

        if ([] === $words) {
            return new WebsiteResults(0, []);
        }

        // A new installation, or one after "search:rebuild", fills itself once.
        if ($index->isEmpty(SearchIndex::LIVE)) {
            $indexer->rebuild(null, SearchIndex::LIVE);
        }

        $hits = [];

        foreach ($index->candidates($words, null, SearchIndex::LIVE, $language) as $row) {
            $target = \is_string($row['target'] ?? null) ? Target::tryParse($row['target']) : null;
            $address = null === $target ? null : $urls->addresses($target)[$language] ?? null;

            if (null === $target || null === $address || $address->fallback) {
                continue;
            }

            // Objects lie on their base page, pages on themselves.
            $page = $address->basePageId ?? ($target->isPage() ? $target->id : null);

            if (null === $page || !self::visible($outline, $page)) {
                continue;
            }

            $title = \is_string($row['title'] ?? null) ? $row['title'] : '';
            $terms = \is_string($row['terms'] ?? null) ? $row['terms'] : '';
            $key = (string) $target;

            if (isset($hits[$key])) {
                continue;
            }

            $hits[$key] = [
                'row' => $row,
                'target' => $target,
                'score' => SearchRanking::score(['title' => $title, 'terms' => $terms], $words),
            ];
        }

        uasort($hits, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $result = [];

        foreach (\array_slice($hits, $this->offset, $this->limit) as $hit) {
            $row = $hit['row'];
            $title = \is_string($row['title'] ?? null) ? $row['title'] : '';
            $url = $urls->href($hit['target'], $language);

            if (null === $url) {
                continue;
            }

            $result[] = new WebsiteHit(
                source: \is_string($row['source'] ?? null) ? $row['source'] : '',
                item: \is_string($row['item'] ?? null) ? $row['item'] : '',
                title: $title,
                excerpt: SearchRanking::excerpt(self::withoutTitle(\is_string($row['body'] ?? null) ? $row['body'] : '', $title), $words, $this->q),
                url: $url,
                score: $hit['score'],
            );
        }

        return new WebsiteResults(\count($hits), $result);
    }

    /**
     * The text without lines that repeat the title, e.g. the heading of a
     * page, so the excerpt adds something.
     */
    private static function withoutTitle(string $body, string $title): string
    {
        return implode("\n", array_filter(explode("\n", $body), static fn(string $line): bool => trim($line) !== trim($title)));
    }

    private static function visible(PageOutline $outline, string $page): bool
    {
        return null !== $outline->get($page)
            && null === $outline->lockedBy($page)
            && null === $outline->excludedFromSiteSearchBy($page)
            // Nothing of a page with a password in the results (decision 0358).
            && null === $outline->protectedBy($page);
    }
}
