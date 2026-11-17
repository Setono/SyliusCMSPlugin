<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * What a Snippet field's picker finds: the snippets whose declared context the edited entry provides, but the entry
 * itself. See "Snippet references" in docs/spec/builder.md and "Snippets and their context" in docs/spec/slots.md
 */
final class SnippetSearchTest extends BuilderApiTestCase
{
    /**
     * @test
     */
    public function it_finds_snippets_by_title_ignoring_case_published_or_not_newest_first(): void
    {
        $client = self::signedIn();
        $footer = self::createSnippet('Footer', published: true);
        $links = self::createSnippet('Footer links');
        self::createSnippet('Opening hours');
        self::createPage('Footer page', 'footer');

        self::assertSame([
            ['ref' => $links->getUuid(), 'name' => 'Footer links', 'thumbnail' => null],
            ['ref' => $footer->getUuid(), 'name' => 'Footer', 'thumbnail' => null],
        ], self::json($client, 'GET', '/admin/cms/api/search/snippet?q=FOOT'));
    }

    /**
     * @test
     */
    public function it_finds_only_the_snippets_whose_declared_context_the_entry_provides_but_the_entry_itself(): void
    {
        $client = self::signedIn();
        $footer = self::createSnippet('Footer');
        $sizeGuide = self::createSnippet('Size guide', context: ['product']);
        $sidebar = self::createSnippet('Taxon sidebar', context: ['product', 'taxon']);

        self::assertSame([$footer->getUuid()], self::found($client, '?q=&provides=entry'));
        self::assertSame([$footer->getUuid()], self::found($client, '?q=&provides='));
        self::assertSame([$sizeGuide->getUuid(), $footer->getUuid()], self::found($client, '?q=&provides=product'));
        self::assertSame([$sizeGuide->getUuid(), $footer->getUuid()], self::found($client, sprintf('?q=&provides=product,taxon&entry=%s', $sidebar->getUuid())));
        self::assertSame([$sidebar->getUuid(), $sizeGuide->getUuid(), $footer->getUuid()], self::found($client, '?q='));
    }

    /**
     * @test
     */
    public function it_looks_snippets_up_by_uuid_whatever_their_context(): void
    {
        $client = self::signedIn();
        $footer = self::createSnippet('Footer');
        $sizeGuide = self::createSnippet('Size guide', context: ['product']);
        $page = self::createPage('About us', 'about-us');

        self::assertEqualsCanonicalizing([
            ['ref' => $footer->getUuid(), 'name' => 'Footer', 'thumbnail' => null],
            ['ref' => $sizeGuide->getUuid(), 'name' => 'Size guide', 'thumbnail' => null],
        ], self::json($client, 'GET', sprintf('/admin/cms/api/search/snippet?refs=%s,%s,%s&provides=entry', $footer->getUuid(), $sizeGuide->getUuid(), $page->getUuid())));
    }

    /**
     * @return list<mixed>
     */
    private static function found(KernelBrowser $client, string $query): array
    {
        return array_column((array) self::json($client, 'GET', '/admin/cms/api/search/snippet' . $query), 'ref');
    }
}
