<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search\Tests;

use Frootbox\Core\Cache\PageCache;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Help\HelpLibrary;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Page\LockPage;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Page\PageOutline;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Page\Reach;
use Frootbox\Core\Page\UpdatePage;
use Frootbox\Core\Search\SearchIndex;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Frootbox\Core\Url\Urls;
use Frootbox\Search\Blocks\Search;
use Frootbox\Search\FindOnWebsite;
use Frootbox\Search\Marks;
use Frootbox\Search\UpdateWebsiteIndex;
use Frootbox\Search\WebsiteHit;
use Frootbox\Search\WebsiteResults;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(FindOnWebsite::class)]
#[CoversClass(UpdateWebsiteIndex::class)]
#[CoversClass(Search::class)]
#[CoversClass(Marks::class)]
final class WebsiteSearchTest extends NotesTestCase
{
    private string $start;

    private string $search;

    protected function setUp(): void
    {
        parent::setUp();

        $this->start = $this->page(null, 'Start', [['block' => 'acme/base/Teaser', 'fields' => ['title' => 'Willkommen in der Rösterei']]]);
        $this->search = $this->page($this->start, 'Suche', [['block' => 'frootbox/search/Search', 'options' => ['perPage' => 2]]]);
        $this->runner()->run(new PublishPages([$this->start, $this->search]));
    }

    public function testFindsPublishedPagesBestFirst(): void
    {
        $roastery = $this->page($this->start, 'Rösterei', [['block' => 'acme/base/Teaser', 'fields' => ['title' => 'Am Hafen', 'text' => self::text('Wir rösten jeden Montag.')]]]);
        $this->page($this->start, 'Rösterei im Entwurf');
        $this->runner()->run(new PublishPages([$roastery]));

        $results = $this->find('rösterei');

        self::assertSame(2, $results->total, 'Drafts are not found.');
        self::assertSame([['Rösterei', '/roesterei'], ['Start', '/']], array_map(static fn(WebsiteHit $hit): array => [$hit->title, $hit->url], $results->hits), 'The title first.');
        self::assertSame('Willkommen in der Rösterei', $results->hits[1]->excerpt);
        self::assertSame(['page', $roastery], [$results->hits[0]->source, $results->hits[0]->item]);
        self::assertSame(0, $this->find('rösterei bahnhof')->total, 'Every word must occur.');
        self::assertSame(1, $this->find('montag')->total, 'The text of the blocks counts.');
    }

    public function testFollowsWhatIsPublished(): void
    {
        self::assertSame(0, $this->find('espresso')->total, 'Fills the index on the first search.');

        $id = $this->page($this->start, 'Espresso');
        self::assertSame(0, $this->find('espresso')->total);

        $this->runner()->run(new PublishPages([$id]));
        self::assertSame(['Espresso'], $this->titles('espresso'));

        $this->runner()->run(new UpdatePage($id, ['de' => ['title' => 'Ristretto', 'content' => ['fields' => ['heading' => 'Ristretto']]]]));
        self::assertSame(['Espresso'], $this->titles('espresso'), 'A draft changes nothing.');

        $this->runner()->run(new PublishPages([$id]));
        self::assertSame([], $this->titles('espresso'));
        self::assertSame(['Ristretto'], $this->titles('ristretto'));
    }

    public function testLeavesOutLockedAndExcludedPages(): void
    {
        $about = $this->page($this->start, 'Über uns Kaffee');
        $team = $this->page($about, 'Team Kaffee');
        $shop = $this->page($this->start, 'Laden Kaffee');
        $this->runner()->run(new PublishPages([$about, $team, $shop]));
        self::assertSame(3, $this->find('kaffee')->total);

        $this->runner()->run(new UpdatePage($about, excludeFromSiteSearch: Reach::Branch));
        $this->runner()->run(new PublishPages([$about]));
        self::assertSame(['Laden Kaffee'], $this->titles('kaffee'), 'The branch is left out.');

        $this->runner()->run(new LockPage($shop));
        self::assertSame([], $this->titles('kaffee'), 'Locked pages too.');
    }

    public function testSearchesTheLanguageOfTheVisitor(): void
    {
        $id = $this->runner()->run(new CreatePage('acme/base/Page', [
            'de' => ['title' => 'Kaffee', 'content' => ['fields' => ['heading' => 'Kaffee']]],
            'en' => ['title' => 'Coffee', 'content' => ['fields' => ['heading' => 'Coffee']]],
        ], $this->start))->id;
        $this->runner()->run(new PublishPages([$id]));

        self::assertSame([], $this->titles('coffee'));
        self::assertSame(['Coffee'], array_map(static fn(WebsiteHit $hit): string => $hit->title, $this->find('coffee', 'en')->hits));
        self::assertSame('/en/coffee', $this->find('coffee', 'en')->hits[0]->url ?? null);
        self::assertSame(0, $this->find('rösterei', 'en')->total, 'Not the German start page shown in English.');
    }

    public function testTheBlockShowsTheHitsPageByPage(): void
    {
        foreach (['Kaffee eins', 'Kaffee zwei', 'Kaffee drei'] as $title) {
            $this->runner()->run(new PublishPages([$this->page($this->start, $title, [['block' => 'acme/base/Teaser', 'fields' => ['title' => 'Frischer Kaffee aus Hamburg']]])]));
        }

        $empty = $this->request('/suche');
        self::assertSame('miss', $empty->getHeaderLine(PageCache::HEADER), 'Without words the page is cached.');
        self::assertStringContainsString('<form class="search__form" role="search" action="/suche" method="get">', (string) $empty->getBody());
        self::assertStringNotContainsString('role="status"', (string) $empty->getBody());

        $response = $this->request('/suche?q=Kaffee');
        $html = (string) $response->getBody();
        self::assertSame('', $response->getHeaderLine(PageCache::HEADER), 'Results are never cached.');
        self::assertStringContainsString('value="Kaffee"', $html);
        self::assertStringContainsString('<p class="search__status" role="status">3 Treffer zu „Kaffee“.</p>', $html);
        self::assertMatchesRegularExpression('~<h\d class="search-hit__title"><a href="/kaffee-[a-z]+">Kaffee [a-z]+</a></h\d>~', $html);
        self::assertStringContainsString('Frischer <mark>Kaffee</mark> aus Hamburg', $html);
        self::assertStringContainsString('href="/suche?q=Kaffee&amp;page=2" rel="next"', $html);
        self::assertSame(1, substr_count((string) $this->request('/suche?q=Kaffee&page=2')->getBody(), 'class="search-hit"'));

        self::assertStringContainsString('Zu „Tee“ wurde nichts gefunden.', (string) $this->request('/suche?q=Tee')->getBody());
        self::assertStringContainsString('Ein Treffer zu „zwei“.', (string) $this->request('/suche?q=zwei')->getBody());
    }

    public function testMarksTheWordsInTheExcerpt(): void
    {
        self::assertSame([
            ['text' => 'Frische ', 'mark' => false],
            ['text' => 'Bohnen', 'mark' => true],
            ['text' => ' vom ', 'mark' => false],
            ['text' => 'Hafen', 'mark' => true],
        ], Marks::parts('Frische Bohnen vom Hafen', 'hafen bohnen'));
        self::assertSame([['text' => 'A b', 'mark' => false]], Marks::parts('A b', 'a'), 'Single letters are not marked.');
        self::assertSame([], Marks::parts('', 'a'));
    }

    public function testTheIndexOfTheWebsiteCanBeEmptied(): void
    {
        $this->runner()->run(new PublishPages([$this->page($this->start, 'Kakao')]));
        self::assertSame(['Kakao'], $this->titles('kakao'));

        $this->get(SearchIndex::class)->clear(null, SearchIndex::LIVE);
        self::assertSame(['Kakao'], $this->titles('kakao'), 'It fills itself again.');
    }

    public function testBringsItsHelpInEnglishAndGerman(): void
    {
        $help = $this->get(HelpLibrary::class)->get('frootbox/search/search');

        self::assertNotNull($help);
        self::assertSame(['Search on the website', 'Suche auf der Website'], [$help->title('en'), $help->title('de')]);
    }

    protected function environment(): Environment
    {
        return Environment::Production;
    }

    protected function extensions(): Extensions
    {
        $search = ExtensionPackage::fromManifest('frootbox/search', (string) realpath(__DIR__ . '/..'), psr4: ['Frootbox\Search\\' => ['src/'], 'Frootbox\Search\Migrations\\' => ['migrations/']]);

        return new Extensions([...FixtureSite::extensions('base', 'site')->packages, $search], 'search');
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function page(?string $parentId, string $title, array $blocks = []): string
    {
        return $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => $title, 'content' => ['fields' => ['heading' => $title], 'areas' => ['main' => $blocks]]]], $parentId))->id;
    }

    private function find(string $q, string $language = 'de'): WebsiteResults
    {
        // As in a new request, see PageHandler.
        $this->get(PageOutline::class)->clear();
        $this->get(Urls::class)->clear();

        return $this->runner()->ask(new FindOnWebsite($q, $language));
    }

    /**
     * @return list<string>
     */
    private function titles(string $q): array
    {
        return array_map(static fn(WebsiteHit $hit): string => $hit->title, $this->find($q)->hits);
    }

    private function request(string $path): ResponseInterface
    {
        $request = new ServerRequest('GET', 'https://example.org' . $path);
        parse_str($request->getUri()->getQuery(), $query);

        return $this->get(PageHandler::class)->handle($request->withQueryParams($query));
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(string $paragraph): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]]]];
    }
}
