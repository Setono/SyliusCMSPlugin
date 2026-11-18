<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

final class StoredTreeTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    /**
     * @test
     */
    public function it_finds_a_block_by_its_key_at_any_depth(): void
    {
        $child = new StoredBlock(self::B, 'column', 1);
        $parent = new StoredBlock(self::A, 'columns', 1);
        $parent->children = [$child];
        $tree = new StoredTree(null, [$parent]);

        self::assertSame($parent, $tree->findBlock(self::A));
        self::assertSame($child, $tree->findBlock(self::B));
        self::assertNull($tree->findBlock(self::C));
    }

    /**
     * @test
     */
    public function it_inserts_a_top_level_block_at_an_index(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1), new StoredBlock(self::C, 'heading', 1)]);

        $tree->insertBlock(new StoredBlock(self::B, 'heading', 1), 1);

        self::assertSame([self::A, self::B, self::C], self::keys($tree->blocks));
    }

    /**
     * @test
     */
    public function it_inserts_a_block_into_the_children_of_a_block_at_any_depth(): void
    {
        $inner = new StoredBlock(self::B, 'column', 1, children: [new StoredBlock(self::C, 'heading', 1)]);
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'columns', 1, children: [$inner])]);
        $first = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e41';
        $last = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e42';

        self::assertTrue($tree->insertBlock(new StoredBlock($first, 'heading', 1), 0, self::B));
        self::assertTrue($tree->insertBlock(new StoredBlock($last, 'heading', 1), 10, self::B));

        self::assertSame([$first, self::C, $last], self::keys($inner->children));
    }

    /**
     * @test
     */
    public function it_inserts_nothing_into_a_block_it_does_not_have(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        self::assertFalse($tree->insertBlock(new StoredBlock(self::B, 'heading', 1), 0, self::C));
        self::assertSame([self::A], $tree->keys());
    }

    /**
     * @test
     */
    public function it_clamps_the_insert_index_to_the_list(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        $tree->insertBlock(new StoredBlock(self::C, 'heading', 1), 10);
        $tree->insertBlock(new StoredBlock(self::B, 'heading', 1), -3);

        self::assertSame([self::B, self::A, self::C], self::keys($tree->blocks));
    }

    /**
     * @test
     */
    public function it_removes_a_block_at_any_depth(): void
    {
        $parent = new StoredBlock(self::A, 'columns', 1);
        $parent->children = [new StoredBlock(self::B, 'column', 1), new StoredBlock(self::C, 'column', 1)];
        $tree = new StoredTree(null, [$parent]);

        self::assertTrue($tree->removeBlock(self::B));
        self::assertSame([self::C], self::keys($parent->children));
        self::assertTrue($tree->removeBlock(self::A));
        self::assertSame([], $tree->blocks);
        self::assertFalse($tree->removeBlock(self::A));
    }

    /**
     * @test
     */
    public function it_sets_and_clears_a_shared_value(): void
    {
        $block = new StoredBlock(self::A, 'heading', 1);

        $block->setValue('level', null, 'h3');
        self::assertSame(['level' => 'h3'], $block->fields);

        $block->setValue('level', null, null);
        self::assertSame([], $block->fields);
    }

    /**
     * @test
     */
    public function it_keeps_a_shared_false_or_zero(): void
    {
        $block = new StoredBlock(self::A, 'banner', 1);

        $block->setValue('fullWidth', null, false);
        $block->setValue('count', null, 0);

        self::assertSame(['fullWidth' => false, 'count' => 0], $block->fields);
    }

    /**
     * @test
     */
    public function it_sets_and_clears_a_translatable_value_per_locale(): void
    {
        $block = new StoredBlock(self::A, 'heading', 1);

        $block->setValue('text', 'en_US', 'Hello');
        $block->setValue('text', 'da_DK', 'Hej');
        self::assertSame(['text' => ['en_US' => 'Hello', 'da_DK' => 'Hej']], $block->fields);

        $block->setValue('text', 'en_US', '');
        self::assertSame(['text' => ['da_DK' => 'Hej']], $block->fields);

        $block->setValue('text', 'da_DK', null);
        self::assertSame([], $block->fields);
    }

    /**
     * @test
     */
    public function it_replaces_a_shared_value_with_a_locale_map_when_a_locale_is_given(): void
    {
        $block = new StoredBlock(self::A, 'heading', 1);
        $block->fields = ['text' => 'Hello'];

        $block->setValue('text', 'en_US', 'Hi');

        self::assertSame(['text' => ['en_US' => 'Hi']], $block->fields);
    }

    /**
     * @test
     */
    public function it_sets_and_clears_where_a_value_was_translated_from_per_locale(): void
    {
        $source = ['from' => 'en_US', 'fingerprint' => hash('sha256', '"Hello"')];
        $block = new StoredBlock(self::A, 'heading', 1);
        $block->translated = ['subtitle' => 'not a locale map'];

        $block->setTranslated('text', 'da_DK', $source);
        $block->setTranslated('text', 'de_DE', $source);
        $block->setTranslated('subtitle', 'da_DK', $source);

        self::assertSame(['subtitle' => ['da_DK' => $source], 'text' => ['da_DK' => $source, 'de_DE' => $source]], $block->translated);

        $block->setTranslated('text', 'da_DK', null);
        $block->setTranslated('subtitle', 'da_DK', null);

        self::assertSame(['text' => ['de_DE' => $source]], $block->translated);
    }

    /**
     * @test
     */
    public function it_lists_every_key_in_the_tree(): void
    {
        $parent = new StoredBlock(self::A, 'columns', 1);
        $parent->children = [new StoredBlock(self::B, 'column', 1)];
        $tree = new StoredTree(null, [$parent, new StoredBlock(self::C, 'heading', 1)]);

        self::assertSame([self::A, self::B, self::C], $tree->keys());
        self::assertSame([self::A, self::B], $parent->keys());
    }

    /**
     * @test
     */
    public function it_lists_keys_depth_first_through_several_children(): void
    {
        $d = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';
        $b = new StoredBlock(self::B, 'column', 1);
        $b->children = [new StoredBlock($d, 'heading', 1)];
        $a = new StoredBlock(self::A, 'columns', 1);
        $a->children = [$b, new StoredBlock(self::C, 'column', 1)];

        self::assertSame([self::A, self::B, $d, self::C], $a->keys());
    }

    /**
     * @test
     */
    public function it_lists_every_block_depth_first(): void
    {
        $d = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d', 'heading', 1);
        $b = new StoredBlock(self::B, 'column', 1);
        $b->children = [$d];
        $a = new StoredBlock(self::A, 'columns', 1);
        $a->children = [$b];
        $c = new StoredBlock(self::C, 'heading', 1);

        self::assertSame([$a, $b, $d, $c], (new StoredTree(null, [$a, $c]))->allBlocks());
        self::assertSame([], (new StoredTree(null, []))->allBlocks());
    }

    /**
     * @test
     */
    public function its_clone_is_a_deep_copy(): void
    {
        $child = new StoredBlock(self::B, 'column', 1);
        $parent = new StoredBlock(self::A, 'columns', 1);
        $parent->children = [$child];
        $tree = new StoredTree(new StoredLayout('standard', 1), [$parent]);

        $copy = clone $tree;
        self::assertNotNull($copy->layout);
        $copy->layout->fields = ['showTitle' => false];
        $copy->findBlock(self::B)?->setValue('text', 'en_US', 'Hello');
        $copy->insertBlock(new StoredBlock(self::C, 'heading', 1), 1);

        self::assertNotNull($tree->layout);
        self::assertSame([], $tree->layout->fields);
        self::assertSame([], $child->fields);
        self::assertSame([self::A, self::B], $tree->keys());
        self::assertSame([self::A, self::B, self::C], $copy->keys());
    }

    /**
     * @test
     */
    public function it_clones_a_tree_without_a_layout(): void
    {
        self::assertNull((clone new StoredTree(null, []))->layout);
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<string>
     */
    private static function keys(array $blocks): array
    {
        return array_map(static fn (StoredBlock $block): string => $block->key, $blocks);
    }
}
