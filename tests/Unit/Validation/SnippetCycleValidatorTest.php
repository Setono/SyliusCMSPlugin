<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\SnippetCyclesTest;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\SnippetCycleValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;

/**
 * Publish refuses a snippet whose Snippet blocks lead back to it, directly or through the published trees of the
 * snippets they show, naming the way. See "Validation" in docs/spec/publishing.md and "Snippet" in docs/spec/blocks.md
 */
final class SnippetCycleValidatorTest extends TestCase
{
    use ProphecyTrait;

    private const SHOWS_PROMO = '0193a5be-0000-7000-8000-0000000000c1';

    private const SHOWS_FOOTER = '0193a5be-0000-7000-8000-0000000000c2';

    private const SHOWS_ITSELF = '0193a5be-0000-7000-8000-0000000000c3';

    /**
     * @test
     */
    public function it_refuses_each_snippet_block_that_leads_back_to_the_snippet_naming_the_way_by_the_snippets_titles(): void
    {
        $footer = self::snippet('Footer');
        $promo = self::snippet('Promo');
        $promo->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_FOOTER, $footer)]));
        $promo->publish(new \DateTimeImmutable('2026-11-18 10:00:00'));
        $footer->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_PROMO, $promo), Blocks::snippet(self::SHOWS_ITSELF, $footer)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$promo->getUuid()])->willReturn([$promo]);

        self::assertEquals([
            new ValidationError(self::SHOWS_PROMO, 'snippet', null, 'It would show this snippet inside itself: Footer → Promo → Footer. Pick another snippet.'),
            new ValidationError(self::SHOWS_ITSELF, 'snippet', null, 'It would show this snippet inside itself: Footer → Footer. Pick another snippet.'),
        ], self::validator($entries->reveal())->validate($footer));
    }

    /**
     * @test
     */
    public function it_accepts_a_snippet_whose_snippet_blocks_never_lead_back_to_it(): void
    {
        $footer = self::snippet('Footer');
        $promo = self::snippet('Promo');
        $promo->publish(new \DateTimeImmutable('2026-11-18 10:00:00'));
        $footer->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_PROMO, $promo)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$promo->getUuid()])->willReturn([$promo]);

        self::assertSame([], self::validator($entries->reveal())->validate($footer));
    }

    /**
     * Only snippets show snippets, so a page can't be inside one
     *
     * @test
     */
    public function it_reads_nothing_of_an_entry_that_is_not_a_snippet(): void
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_PROMO, self::snippet('Promo'))]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], self::validator($entries->reveal())->validate($page));
    }

    private static function snippet(string $title): Snippet
    {
        $snippet = new Snippet();
        $snippet->setCurrentLocale('en_US');
        $snippet->setFallbackLocale('en_US');
        $snippet->setTitle($title);

        return $snippet;
    }

    private static function validator(EntryRepositoryInterface $entries): SnippetCycleValidator
    {
        return new SnippetCycleValidator(SnippetCyclesTest::snippetCycles($entries), BlockTreeValidatorTest::translator());
    }
}
