<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\SnippetCycle;
use Setono\SyliusCMSPlugin\Tree\SnippetCycles;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The Snippet blocks through which a snippet would show inside itself, directly or through the snippets they show, as
 * those are published. See "Snippet" in docs/spec/blocks.md
 */
final class SnippetCyclesTest extends TestCase
{
    use ProphecyTrait;

    private const SHOWS_ITSELF = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const SHOWS_B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const SHOWS_C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const SHOWS_A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const SHOWS_B_AGAIN = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_finds_a_snippet_block_that_shows_the_snippet_it_is_in_with_no_snippet_between(): void
    {
        $a = self::published();
        $tree = new StoredTree(null, [self::heading(), Blocks::snippet(self::SHOWS_ITSELF, $a)]);

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([[self::SHOWS_ITSELF, 'snippet', []]], self::described($this->cycles($entries->reveal())->in($a, $tree)));
    }

    /**
     * @test
     */
    public function it_finds_the_way_back_through_the_published_trees_of_the_snippets_it_shows_reading_each_level_with_one_query(): void
    {
        $a = self::published();
        $b = self::published([]);
        $c = self::published([]);
        $b->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_C, $c)]));
        $c->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_A, $a)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$b->getUuid()])->willReturn([$b])->shouldBeCalledOnce();
        $entries->findByUuids([$c->getUuid()])->willReturn([$c])->shouldBeCalledOnce();

        $cycles = $this->cycles($entries->reveal())->in($a, new StoredTree(null, [self::heading(), Blocks::snippet(self::SHOWS_B, $b), Blocks::snippet(self::SHOWS_B_AGAIN, $b)]));

        self::assertSame([[self::SHOWS_B, 'snippet', [$b, $c]], [self::SHOWS_B_AGAIN, 'snippet', [$b, $c]]], self::described($cycles));
    }

    /**
     * A snippet that was never published shows nothing, so it leads nowhere, whatever its draft holds
     *
     * @test
     */
    public function it_follows_only_snippets_that_are_published(): void
    {
        $a = self::published();
        $b = new Snippet();
        $b->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_A, $a)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$b->getUuid()])->willReturn([$b]);

        self::assertSame([], $this->cycles($entries->reveal())->in($a, new StoredTree(null, [Blocks::snippet(self::SHOWS_B, $b)])));
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_the_snippets_it_shows_never_show_it_even_when_they_show_each_other(): void
    {
        $a = self::published();
        $b = self::published([]);
        $c = self::published([]);
        $b->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_C, $c)]));
        $c->setBlockTree(new StoredTree(null, [Blocks::snippet(self::SHOWS_B, $b)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids([$b->getUuid()])->willReturn([$b]);
        $entries->findByUuids([$c->getUuid()])->willReturn([$c]);

        self::assertSame([], $this->cycles($entries->reveal())->in($a, new StoredTree(null, [Blocks::snippet(self::SHOWS_B, $b)])));
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function published(array $blocks = []): Snippet
    {
        $snippet = new Snippet();
        $snippet->setBlockTree(new StoredTree(null, $blocks));
        $snippet->publish(new \DateTimeImmutable('2026-11-18 10:00:00'));

        return $snippet;
    }

    private static function heading(): StoredBlock
    {
        return new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e', 'heading', 1, fields: ['text' => ['en_US' => 'Footer']]);
    }

    /**
     * Each cycle as [its block, its field, the snippets it leads through]
     *
     * @param list<SnippetCycle> $cycles
     *
     * @return list<array{string|null, string, list<Snippet>}>
     */
    private static function described(array $cycles): array
    {
        return array_map(static fn (SnippetCycle $cycle): array => [$cycle->reference->block, $cycle->reference->field, $cycle->through], $cycles);
    }

    private function cycles(EntryRepositoryInterface $entries): SnippetCycles
    {
        return self::snippetCycles($entries);
    }

    /**
     * Snippet cycles reading the snippets they lead through from the repository, in trees of headings and Snippet blocks
     */
    public static function snippetCycles(EntryRepositoryInterface $entries): SnippetCycles
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'snippet' => SnippetBlock::class], new FieldReader());
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
            SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
        ]));

        return new SnippetCycles(new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $handlers), $entries);
    }
}
