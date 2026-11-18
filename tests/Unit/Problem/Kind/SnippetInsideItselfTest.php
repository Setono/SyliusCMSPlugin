<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\SnippetInsideItself;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\SnippetCyclesTest;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

final class SnippetInsideItselfTest extends TestCase
{
    use ProphecyTrait;

    private const SHOWS_ITSELF = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const SHOWS_B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const SHOWS_C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const SHOWS_A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e';

    /**
     * @test
     */
    public function it_flags_a_snippet_block_that_shows_the_snippet_it_is_in(): void
    {
        $a = new Snippet();
        $a->setBlockTree(new StoredTree(null, [self::heading(), Blocks::snippet(self::SHOWS_ITSELF, $a)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::cetera())->shouldNotBeCalled();

        self::assertEquals([self::problem($a, self::SHOWS_ITSELF)], [...$this->kind($entries->reveal())->findIn($a)]);
    }

    /**
     * @test
     */
    public function it_flags_a_snippet_block_whose_snippet_shows_the_snippet_it_is_in_through_other_snippets_as_they_are_published(): void
    {
        $a = new Snippet();
        $b = self::published(new Snippet());
        $c = self::published(new Snippet());
        $a->setBlockTree(new StoredTree(null, [self::heading(), Blocks::snippet(self::SHOWS_B, $b)]));
        $b->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_C, $c)]));
        $c->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_A, $a)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$b->getUuid()])->willReturn([$b])->shouldBeCalledOnce();
        $entries->findByUuids([$c->getUuid()])->willReturn([$c])->shouldBeCalledOnce();

        self::assertEquals([self::problem($a, self::SHOWS_B)], [...$this->kind($entries->reveal())->findIn($a)]);
    }

    /**
     * @test
     */
    public function it_flags_nothing_when_the_snippets_it_shows_never_show_it_even_when_they_show_each_other(): void
    {
        $a = new Snippet();
        $b = self::published(new Snippet());
        $c = self::published(new Snippet());
        $a->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_B, $b)]));
        $b->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_C, $c)]));
        $c->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_B, $b)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$b->getUuid()])->willReturn([$b]);
        $entries->findByUuids([$c->getUuid()])->willReturn([$c]);

        self::assertSame([], [...$this->kind($entries->reveal())->findIn($a)]);
    }

    /**
     * @test
     */
    public function it_flags_nothing_in_an_entry_that_is_not_a_snippet_which_no_snippet_can_show(): void
    {
        $a = new Snippet();
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_A, $a)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind($entries->reveal())->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_counts_again_the_snippets_that_show_a_written_snippet_directly_or_through_other_snippets(): void
    {
        $a = new Snippet();
        $b = new Snippet();
        $c = new Snippet();
        $page = new Page();

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$c->getUuid()])->willReturn([$b])->shouldBeCalledOnce();
        $entries->findReferencing([$b->getUuid()])->willReturn([$a, $page, $c])->shouldBeCalledOnce();
        $entries->findReferencing([$a->getUuid()])->willReturn([$b])->shouldBeCalledOnce();

        self::assertSame([$b, $a], [...$this->kind($entries->reveal())->findEntriesReferencing([$c, $page])]);
    }

    /**
     * @test
     */
    public function it_counts_nothing_again_when_no_snippet_was_written(): void
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind($entries->reveal())->findEntriesReferencing([new Page()])]);
    }

    /**
     * @test
     */
    public function it_reads_entries_as_the_records_their_snippet_blocks_refer_to(): void
    {
        self::assertSame([EntryInterface::class], SnippetInsideItself::getReferencedClasses());
        self::assertSame([], SnippetInsideItself::getReferenceFields(), 'Publishing a snippet changes what it shows, not its UUID');
    }

    private static function problem(SnippetInterface $snippet, string $block): Problem
    {
        return new Problem(
            'snippet_inside_itself',
            Severity::Error,
            $snippet,
            new TranslatableMessage('setono_sylius_cms.problem.snippet_inside_itself', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.snippet')]),
            Fix::inBuilder($snippet, $block),
            $block,
            'snippet',
        );
    }

    private static function published(Snippet $snippet): Snippet
    {
        $snippet->publish(new \DateTimeImmutable('2026-11-18 10:00:00'));

        return $snippet;
    }

    private static function heading(): StoredBlock
    {
        return new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Footer']]);
    }

    private function kind(EntryRepositoryInterface $entries): SnippetInsideItself
    {
        return new SnippetInsideItself(
            SnippetCyclesTest::snippetCycles($entries),
            new BlockTypeRegistry(['heading' => Heading::class, 'snippet' => SnippetBlock::class], new FieldReader()),
            $entries,
        );
    }
}
