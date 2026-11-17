<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\BrokenBlockKernel;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Unbuildable;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\RecordingLogger;
use Setono\SyliusCMSPlugin\Tests\Support\ShareLinks;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\InStock;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A block that throws while rendering drops out in production and is logged, while the rest of the snippet and the page
 * still show, and in dev the exception is thrown. Share links handle it as the shop does. See "Failures" in
 * docs/spec/rendering.md
 */
final class RenderingFailuresTest extends WebTestCase
{
    use Entries;
    use Placements;
    use ShareLinks;

    private const BEFORE = '0193a5be-0000-7000-8000-000000000001';

    private const BROKEN = '0193a5be-0000-7000-8000-000000000002';

    private const AFTER = '0193a5be-0000-7000-8000-000000000003';

    private const GROUP = '0193a5be-0000-7000-8000-000000000004';

    private const REFERENCE = '0193a5be-0000-7000-8000-000000000005';

    private const ERROR = 'Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Broken has no field "missing"';

    protected static function getKernelClass(): string
    {
        return BrokenBlockKernel::class;
    }

    /**
     * @test
     */
    public function it_drops_a_block_that_throws_and_logs_it_with_its_entry_its_key_and_its_block_type_while_its_neighbours_render(): void
    {
        $client = self::createClient(['debug' => false]);
        $page = self::createPageWithBlocks('About us', 'about-us', self::brokenBetweenHeadings(), published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], self::headings($crawler, '.cms-tree'));
        self::assertSelectorNotExists('.cms-broken');
        self::assertSelectorExists('#footer');
        self::assertSame([[
            'entry' => $page->getUuid(),
            'key' => self::BROKEN,
            'type' => 'broken',
            'message' => self::ERROR,
        ]], self::loggedFailures());
    }

    /**
     * @test
     */
    public function it_drops_only_the_block_that_throws_from_the_block_it_sits_in(): void
    {
        $client = self::createClient(['debug' => false]);
        $group = new StoredBlock(self::GROUP, 'group', 1, children: self::brokenBetweenHeadings());
        self::createPageWithBlocks('About us', 'about-us', [$group], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], self::headings($crawler, '.cms-tree > .cms-group'));
        self::assertSelectorNotExists('.cms-broken');
    }

    /**
     * Fields are hydrated before the templates render, which counts as rendering the block
     *
     * @test
     */
    public function it_drops_a_block_that_throws_while_it_is_hydrated_and_logs_it(): void
    {
        $client = self::createClient(['debug' => false]);
        $page = self::createPageWithBlocks('About us', 'about-us', [self::heading(self::BEFORE, 'Before'), new StoredBlock(self::BROKEN, 'unbuildable', 1), self::heading(self::AFTER, 'After')], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], self::headings($crawler, '.cms-tree'));
        self::assertSame([[
            'entry' => $page->getUuid(),
            'key' => self::BROKEN,
            'type' => 'unbuildable',
            'message' => Unbuildable::MESSAGE,
        ]], self::loggedFailures());
    }

    /**
     * @test
     */
    public function it_throws_what_a_block_threw_with_debug_on(): void
    {
        $client = self::createClient(['debug' => true]);
        $client->catchExceptions(false);
        self::createPageWithBlocks('About us', 'about-us', self::brokenBetweenHeadings(), published: true);

        try {
            $client->request('GET', '/en_US/about-us');
            self::fail('The exception was thrown');
        } catch (\Throwable $e) {
            self::assertStringContainsString(self::ERROR, $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_drops_a_block_that_throws_from_a_snippet_in_a_slot_and_logs_it_with_the_snippet_while_the_rest_of_the_snippet_shows(): void
    {
        $client = self::createClient(['debug' => false]);
        $snippet = self::createSnippet('Footer', self::brokenBetweenHeadings(), published: true);
        self::place($snippet, 'shop.footer');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], self::headings($crawler, '.cms-slot--shop-footer'));
        self::assertSame([[
            'entry' => $snippet->getUuid(),
            'key' => self::BROKEN,
            'type' => 'broken',
            'message' => self::ERROR,
        ]], self::loggedFailures());
    }

    /**
     * Such as a project's condition kind asking a service that's down
     *
     * @test
     */
    public function it_leaves_out_a_placement_whose_page_scope_condition_throws_and_logs_it_while_the_rest_of_the_slot_and_the_page_show(): void
    {
        $client = self::createClient(['debug' => false]);
        $inStock = self::place(self::createSnippet('In stock', [self::heading(self::BEFORE, 'In stock')], published: true), 'shop.footer');
        self::scope($inStock, [['kind' => 'in_stock', 'negate' => false, 'params' => []]]);
        self::place(self::createSnippet('Free returns', [self::heading(self::AFTER, 'Free returns')], published: true), 'shop.footer');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Free returns'], self::headings($crawler, '.cms-slot--shop-footer'));
        self::assertSelectorExists('#footer');
        self::assertSame([[
            'entry' => $inStock->getSnippet()->getUuid(),
            'slot' => 'shop.footer',
            'placement' => $inStock->getUuid(),
            'kind' => 'in_stock',
            'message' => InStock::MESSAGE,
        ]], self::loggedFailures());
    }

    /**
     * @test
     */
    public function it_throws_what_a_page_scope_condition_threw_with_debug_on(): void
    {
        $client = self::createClient(['debug' => true]);
        $client->catchExceptions(false);
        $inStock = self::place(self::createSnippet('In stock', [self::heading(self::BEFORE, 'In stock')], published: true), 'shop.footer');
        self::scope($inStock, [['kind' => 'in_stock', 'negate' => false, 'params' => []]]);

        try {
            $client->request('GET', '/en_US/');
            self::fail('The exception was thrown');
        } catch (\Throwable $e) {
            self::assertStringContainsString(InStock::MESSAGE, $e->getMessage());
        }
    }

    /**
     * The block sits in the snippet's tree, so that's where an editor fixes it
     *
     * @test
     */
    public function it_logs_a_block_that_throws_inside_a_snippet_block_with_the_snippet_it_shows(): void
    {
        $client = self::createClient(['debug' => false]);
        $snippet = self::createSnippet('Footer', self::brokenBetweenHeadings(), published: true);
        self::createPageWithBlocks('About us', 'about-us', [self::snippetBlock($snippet)], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], self::headings($crawler, '.cms-tree'));
        self::assertSame([$snippet->getUuid()], array_column(self::loggedFailures(), 'entry'));
    }

    /**
     * @test
     */
    public function it_drops_a_block_that_throws_on_a_share_link_as_the_shop_does(): void
    {
        $client = self::createClient(['debug' => false]);
        $page = self::createPageWithBlocks('About us', 'about-us', self::brokenBetweenHeadings());

        $crawler = $client->request('GET', '/en_US/about-us?_cms_share=' . self::createShareLink($page)->getToken());

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], self::headings($crawler, '.cms-tree'));
        self::assertSelectorNotExists('.cms-broken');
        self::assertSelectorNotExists('[data-cms-block-error]');
        self::assertSame([$page->getUuid()], array_column(self::loggedFailures(), 'entry'));
    }

    /**
     * @return list<StoredBlock>
     */
    private static function brokenBetweenHeadings(): array
    {
        return [self::heading(self::BEFORE, 'Before'), new StoredBlock(self::BROKEN, 'broken', 1), self::heading(self::AFTER, 'After')];
    }

    private static function snippetBlock(SnippetInterface $snippet): StoredBlock
    {
        return new StoredBlock(self::REFERENCE, 'snippet', 1, fields: ['snippet' => $snippet->getUuid()]);
    }

    /**
     * @return array<mixed>
     */
    private static function headings(Crawler $crawler, string $in): array
    {
        return $crawler->filter($in . ' .cms-heading')->each(static fn (Crawler $heading): string => $heading->text());
    }

    /**
     * What was logged at error about blocks and snippets that threw, without the exceptions
     *
     * @return list<array<array-key, mixed>>
     */
    private static function loggedFailures(): array
    {
        $logger = self::getContainer()->get(RecordingLogger::class);
        self::assertInstanceOf(RecordingLogger::class, $logger);

        return array_values(array_map(
            static function (array $context): array {
                unset($context['exception']);

                return $context;
            },
            array_filter($logger->contextsAt('error'), static fn (array $context): bool => isset($context['entry'])),
        ));
    }
}
