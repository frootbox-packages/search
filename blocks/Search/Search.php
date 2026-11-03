<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search\Blocks;

use Frootbox\Core\Action\Runner;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\View\Delivery;
use Frootbox\Search\FindOnWebsite;
use Frootbox\Search\Marks;
use Frootbox\Search\WebsiteHit;

/**
 * The search on the website (decision 0228): a field, and after a search
 * the hits page by page with the words found marked. Results never go
 * into the page cache, every word would be a page of its own.
 */
final readonly class Search implements BlockController
{
    /** More hits are not ranked, see SearchIndex::CANDIDATES. */
    private const int MOST = 200;

    public function __construct(
        private Runner $runner,
        private Urls $urls,
        private Delivery $delivery,
    ) {}

    public function data(BlockContext $context): array
    {
        $base = null === $context->target ? '' : $this->urls->href($context->target, $context->language) ?? '';
        $q = trim(preg_replace('/\s+/u', ' ', $context->query('q') ?? '') ?? '');

        if ('' === $q) {
            return ['action' => $base, 'q' => '', 'searched' => false, 'total' => 0, 'hits' => [], 'pagination' => null];
        }

        $this->delivery->withoutCache();
        $perPage = max(1, min(50, is_numeric($context->options['perPage'] ?? null) ? (int) $context->options['perPage'] : 10));
        $page = max(1, (int) ($context->query('page') ?? 1));
        $offset = min(($page - 1) * $perPage, self::MOST - $perPage);
        $results = $this->runner->ask(new FindOnWebsite($q, $context->language, $perPage, max(0, $offset)));
        $pages = max(1, (int) ceil(min($results->total, self::MOST) / $perPage));
        $page = min($page, $pages);
        $link = static fn(int $page): string => $base . '?' . http_build_query(['q' => $q, ...($page > 1 ? ['page' => $page] : [])]);

        return [
            'action' => $base,
            'q' => $q,
            'searched' => true,
            'total' => $results->total,
            'hits' => array_map(static fn(WebsiteHit $hit): array => [
                'title' => $hit->title,
                'url' => $hit->url,
                'excerpt' => Marks::parts($hit->excerpt, $q),
            ], $results->hits),
            'pagination' => $pages > 1 ? [
                'page' => $page,
                'pages' => $pages,
                'previous' => $page > 1 ? $link($page - 1) : null,
                'next' => $page < $pages ? $link($page + 1) : null,
            ] : null,
        ];
    }
}
