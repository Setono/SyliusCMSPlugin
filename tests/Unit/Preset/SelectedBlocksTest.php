<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\SelectedBlocks;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The blocks a ⌘-click selection saves as a preset: the selected blocks under different parents in tree order, each with
 * its children, and a block inside another selected block once, as part of its parent. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
final class SelectedBlocksTest extends TestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const COLUMNS = '0193a5be-0000-7000-8000-000000000002';

    private const LEFT = '0193a5be-0000-7000-8000-000000000003';

    private const RIGHT = '0193a5be-0000-7000-8000-000000000004';

    private const IMAGE = '0193a5be-0000-7000-8000-000000000005';

    private const BUTTON = '0193a5be-0000-7000-8000-000000000006';

    private const RICH_TEXT = '0193a5be-0000-7000-8000-000000000007';

    /**
     * @test
     */
    public function it_saves_one_selected_block_with_its_children(): void
    {
        $blocks = SelectedBlocks::in(self::tree(), [self::COLUMNS]);

        self::assertSame([self::COLUMNS], self::keysOf($blocks));
        self::assertSame([self::COLUMNS, self::LEFT, self::IMAGE, self::RIGHT, self::BUTTON], $blocks[0]->keys());
    }

    /**
     * @test
     */
    public function it_saves_blocks_under_different_parents_in_tree_order_whatever_order_they_were_selected_in(): void
    {
        $blocks = SelectedBlocks::in(self::tree(), [self::RICH_TEXT, self::BUTTON, self::HEADING, self::IMAGE]);

        self::assertSame([self::HEADING, self::IMAGE, self::BUTTON, self::RICH_TEXT], self::keysOf($blocks));
        self::assertSame([], $blocks[1]->children);
    }

    /**
     * @test
     */
    public function it_saves_a_block_inside_another_selected_block_once_as_part_of_its_parent(): void
    {
        $blocks = SelectedBlocks::in(self::tree(), [self::IMAGE, self::COLUMNS, self::LEFT, self::RICH_TEXT]);

        self::assertSame([self::COLUMNS, self::RICH_TEXT], self::keysOf($blocks));
        self::assertSame([self::COLUMNS, self::LEFT, self::IMAGE, self::RIGHT, self::BUTTON], $blocks[0]->keys());
    }

    /**
     * @test
     */
    public function it_copies_the_blocks_so_the_tree_is_left_as_it_is(): void
    {
        $tree = self::tree();

        $blocks = SelectedBlocks::in($tree, [self::COLUMNS]);
        $blocks[0]->children = [];

        self::assertCount(2, $tree[1]->children);
    }

    /**
     * @test
     */
    public function it_refuses_a_selection_with_a_block_the_tree_does_not_have(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no block 0193a5be-0000-7000-8000-0000000000ff in the tree');

        SelectedBlocks::in(self::tree(), [self::HEADING, '0193a5be-0000-7000-8000-0000000000ff']);
    }

    /**
     * @test
     */
    public function it_refuses_an_empty_selection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SelectedBlocks::in(self::tree(), []);
    }

    /**
     * Heading, Columns (Column: Image, Column: Button), Rich text
     *
     * @return list<StoredBlock>
     */
    private static function tree(): array
    {
        return [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Winter sale']]),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: [
                new StoredBlock(self::LEFT, 'column', 1, children: [new StoredBlock(self::IMAGE, 'image', 1)]),
                new StoredBlock(self::RIGHT, 'column', 1, children: [new StoredBlock(self::BUTTON, 'button', 1)]),
            ]),
            new StoredBlock(self::RICH_TEXT, 'rich_text', 1),
        ];
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<string>
     */
    private static function keysOf(array $blocks): array
    {
        return array_map(static fn (StoredBlock $block): string => $block->key, $blocks);
    }
}
