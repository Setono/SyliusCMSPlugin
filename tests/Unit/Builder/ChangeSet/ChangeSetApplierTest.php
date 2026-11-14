<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\AddBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Change;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\MoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\RemoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetStyle;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet\Fixture\SharedNote;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class ChangeSetApplierTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const DIRTY = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)'], 'content' => [['type' => 'text', 'text' => 'Hi', 'marks' => [['type' => 'highlight']]]]],
        ['type' => 'iframe', 'attrs' => ['src' => 'https://example.com']],
    ]];

    private const CLEAN = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]];

    /**
     * @test
     */
    public function it_adds_a_block_at_the_top_level(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        $tree = $this->apply($tree, new AddBlock(null, 0, new StoredBlock(self::B, 'heading', 1)));

        self::assertSame([self::B, self::A], $tree->keys());
    }

    /**
     * @test
     */
    public function it_adds_a_block_inside_another_at_an_index(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [new StoredBlock(self::B, 'heading', 1)])]);

        $tree = $this->apply($tree, new AddBlock(self::A, 0, new StoredBlock(self::C, 'heading', 1)));

        self::assertSame([self::C, self::B], array_map(static fn (StoredBlock $block): string => $block->key, $tree->blocks[0]->children));
    }

    /**
     * @test
     */
    public function it_puts_an_added_block_right_after_the_block_it_goes_after_wherever_that_is_now(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::C, 'heading', 1), new StoredBlock(self::A, 'heading', 1)]);

        $tree = $this->apply($tree, new AddBlock(null, 1, new StoredBlock(self::B, 'heading', 1), self::A));

        self::assertSame([self::C, self::A, self::B], $tree->keys());
    }

    /**
     * @test
     */
    public function it_moves_a_block_right_after_the_block_it_goes_after_wherever_that_is_now(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1), new StoredBlock(self::C, 'heading', 1), new StoredBlock(self::B, 'heading', 1)]);

        $tree = $this->apply($tree, new MoveBlock(self::A, null, 0, self::B));

        self::assertSame([self::C, self::B, self::A], $tree->keys());
    }

    /**
     * @test
     */
    public function it_puts_a_block_right_after_the_block_it_goes_after_in_the_middle_of_a_block_s_children(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [new StoredBlock(self::B, 'heading', 1), new StoredBlock(self::C, 'heading', 1)])]);
        $added = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

        $tree = $this->apply($tree, new AddBlock(self::A, 0, new StoredBlock($added, 'heading', 1), self::B));

        self::assertSame([self::A, self::B, $added, self::C], $tree->keys());
    }

    /**
     * @test
     */
    public function it_puts_a_block_at_its_index_when_the_list_does_not_have_the_block_it_goes_after(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [new StoredBlock(self::C, 'heading', 1)])]);

        $tree = $this->apply(
            $tree,
            new AddBlock(self::A, 0, new StoredBlock(self::B, 'heading', 1), self::A),
            new MoveBlock(self::C, null, 5, self::B),
        );

        self::assertSame([self::A, self::B, self::C], $tree->keys());
        self::assertSame(self::B, ($tree->blocks[0]->children[0] ?? null)?->key);
    }

    /**
     * @test
     */
    public function it_moves_a_block_with_its_children_into_another_block(): void
    {
        $moved = new StoredBlock(self::B, 'heading', 1, fields: ['level' => 'h3'], children: [new StoredBlock(self::C, 'heading', 1)]);
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1), $moved]);

        $tree = $this->apply($tree, new MoveBlock(self::B, self::A, 0));

        self::assertCount(1, $tree->blocks);
        self::assertSame([self::A, self::B, self::C], $tree->keys());
        self::assertSame(['level' => 'h3'], $tree->blocks[0]->children[0]->fields);
    }

    /**
     * @test
     */
    public function it_moves_a_block_out_to_the_top_level(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [new StoredBlock(self::B, 'heading', 1)])]);

        $tree = $this->apply($tree, new MoveBlock(self::B, null, 0));

        self::assertSame([self::B, self::A], array_map(static fn (StoredBlock $block): string => $block->key, $tree->blocks));
        self::assertSame([], $tree->blocks[1]->children);
    }

    /**
     * @test
     */
    public function it_reorders_a_list_by_moving_a_block_to_its_index_once_it_is_taken_out(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1), new StoredBlock(self::B, 'heading', 1), new StoredBlock(self::C, 'heading', 1)]);

        $tree = $this->apply($tree, new MoveBlock(self::A, null, 2));

        self::assertSame([self::B, self::C, self::A], $tree->keys());
    }

    /**
     * @test
     */
    public function it_removes_a_block(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1), new StoredBlock(self::B, 'heading', 1)]);

        $tree = $this->apply($tree, new RemoveBlock(self::A));

        self::assertSame([self::B], $tree->keys());
    }

    /**
     * @test
     */
    public function it_sets_values_by_field_and_locale(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        $tree = $this->apply(
            $tree,
            new SetValue(self::A, 'text', 'en_US', 'Hello'),
            new SetValue(self::A, 'level', null, 'h3'),
        );

        self::assertSame(['text' => ['en_US' => 'Hello'], 'level' => 'h3'], $tree->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_sets_a_block_style_from_the_block_type_or_the_config_and_takes_it_away_again(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'group', 1), new StoredBlock(self::B, 'group', 1, style: 'muted')]);

        $tree = $this->apply($tree, new SetStyle(self::A, 'dark'), new SetStyle(self::B, 'brand'));
        self::assertSame('dark', $tree->blocks[0]->style);
        self::assertSame('brand', $tree->blocks[1]->style);

        $tree = $this->apply($tree, new SetStyle(self::A, null));
        self::assertNull($tree->blocks[0]->style);
    }

    /**
     * @test
     */
    public function it_applies_the_changes_in_order(): void
    {
        $tree = new StoredTree(null, []);

        $tree = $this->apply(
            $tree,
            new AddBlock(null, 0, new StoredBlock(self::A, 'heading', 1)),
            new SetValue(self::A, 'text', 'en_US', 'Hel'),
            new SetValue(self::A, 'text', 'en_US', 'Hello'),
        );

        self::assertSame(['text' => ['en_US' => 'Hello']], $tree->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_leaves_the_given_tree_as_it_is(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        $this->apply($tree, new SetValue(self::A, 'text', 'en_US', 'Hello'), new AddBlock(null, 1, new StoredBlock(self::B, 'heading', 1)));

        self::assertSame([self::A], $tree->keys());
        self::assertSame([], $tree->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_applies_nothing_when_one_change_is_invalid(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        try {
            $this->apply($tree, new SetValue(self::A, 'text', 'en_US', 'Hello'), new RemoveBlock(self::B));
            self::fail('The change set must be refused');
        } catch (InvalidChangeSetException) {
        }

        self::assertSame([], $tree->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_refuses_a_kind_of_change_it_does_not_know(): void
    {
        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage('Unknown change');

        self::applier()->apply(
            new ChangeSet([new class() implements Change {
            }]),
            new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]),
        );
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidChanges
     */
    public function it_refuses_a_change_that_does_not_fit_the_tree(Change $change, string $message): void
    {
        $existing = new StoredBlock(self::A, 'heading', 1);
        $unknown = new StoredBlock(self::B, 'removed', 1);
        $tree = new StoredTree(null, [$existing, $unknown]);

        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage($message);

        $this->apply($tree, $change);
    }

    /**
     * @return iterable<string, array{Change, string}>
     */
    public function provideInvalidChanges(): iterable
    {
        $other = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';
        $child = new StoredBlock($other, 'heading', 1);
        $child->children = [new StoredBlock(self::A, 'heading', 1)];
        $withUnknownChild = new StoredBlock($other, 'heading', 1);
        $withUnknownChild->children = [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d', 'nope', 1)];

        yield 'adding a block with a key the tree has' => [new AddBlock(null, 0, new StoredBlock(self::A, 'heading', 1)), 'The tree already has a block with the key ' . self::A];
        yield 'adding a block whose child has a key the tree has' => [new AddBlock(null, 0, $child), 'The tree already has a block with the key ' . self::A];
        yield 'adding a block of an unknown type' => [new AddBlock(null, 0, new StoredBlock($other, 'nope', 1)), '"nope"'];
        yield 'adding a block with a child of an unknown type' => [new AddBlock(null, 0, $withUnknownChild), '"nope"'];
        yield 'adding a block inside a block the tree does not have' => [new AddBlock($other, 0, new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e', 'heading', 1)), $other];
        yield 'moving a block the tree does not have' => [new MoveBlock($other, null, 0), $other];
        yield 'moving a block inside a block the tree does not have' => [new MoveBlock(self::A, $other, 0), $other];
        yield 'moving a block inside itself' => [new MoveBlock(self::A, self::A, 0), 'inside itself'];
        yield 'removing a block the tree does not have' => [new RemoveBlock($other), $other];
        yield 'setting a value on a block the tree does not have' => [new SetValue($other, 'text', 'en_US', 'Hello'), $other];
        yield 'setting a value on a block of an unknown type' => [new SetValue(self::B, 'text', 'en_US', 'Hello'), '"removed"'];
        yield 'setting a field the block type does not have' => [new SetValue(self::A, 'subtitle', 'en_US', 'Hello'), '"subtitle"'];
        yield 'setting a translatable field without a locale' => [new SetValue(self::A, 'text', null, 'Hello'), 'translatable'];
        yield 'setting a shared field for one locale' => [new SetValue(self::A, 'level', 'en_US', 'h3'), 'shared'];
        yield 'setting the style of a block the tree does not have' => [new SetStyle($other, 'dark'), $other];
        yield 'setting the style of a block of an unknown type' => [new SetStyle(self::B, 'dark'), '"removed"'];
        yield 'setting a style the block type does not offer' => [new SetStyle(self::A, 'dark'), 'The block type "heading" has no block style "dark"'];
    }

    private function apply(StoredTree $tree, Change ...$changes): StoredTree
    {
        return self::applier()->apply(new ChangeSet(array_values($changes)), $tree);
    }

    private static function applier(): ChangeSetApplier
    {
        return new ChangeSetApplier(
            new BlockTypeRegistry(
                ['heading' => Heading::class, 'rich_text' => RichText::class, 'shared_note' => SharedNote::class, 'group' => Group::class],
                new FieldReader(),
                styles: ['group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']]],
            ),
            new FieldHandlerLocator(new ServiceLocator([
                ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
                TextHandler::class => static fn (): TextHandler => new TextHandler(),
                ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
                RichTextHandler::class => static fn (): RichTextHandler => new RichTextHandler(new RichTextSchema()),
            ])),
        );
    }

    /**
     * @test
     */
    public function it_sanitises_a_rich_text_value_it_sets(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'rich_text', 1), new StoredBlock(self::B, 'shared_note', 1)]);

        $tree = $this->apply(
            $tree,
            new SetValue(self::A, 'content', 'en_US', self::DIRTY),
            new SetValue(self::B, 'body', null, self::DIRTY),
            new SetValue(self::B, 'caption', null, '<b>Kept as typed</b>'),
        );

        self::assertSame(['content' => ['en_US' => self::CLEAN]], $tree->blocks[0]->fields);
        self::assertSame(['body' => self::CLEAN, 'caption' => '<b>Kept as typed</b>'], $tree->blocks[1]->fields);
    }

    /**
     * @test
     */
    public function it_removes_a_rich_text_value_set_to_null(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'rich_text', 1, fields: ['content' => ['en_US' => self::CLEAN]])]);

        $tree = $this->apply($tree, new SetValue(self::A, 'content', 'en_US', null));

        self::assertSame([], $tree->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_sanitises_the_rich_text_values_of_an_added_block_and_the_blocks_inside_it(): void
    {
        $added = new StoredBlock(self::A, 'rich_text', 1, fields: ['removed' => self::DIRTY, 'content' => ['en_US' => self::DIRTY, 'da_DK' => self::DIRTY]], children: [
            new StoredBlock(self::B, 'shared_note', 1, fields: ['body' => self::DIRTY, 'caption' => '<b>Kept as typed</b>']),
            new StoredBlock(self::C, 'rich_text', 1, fields: ['content' => 'not a locale map']),
        ]);

        $tree = $this->apply(new StoredTree(null, []), new AddBlock(null, 0, $added));

        self::assertSame(['removed' => self::DIRTY, 'content' => ['en_US' => self::CLEAN, 'da_DK' => self::CLEAN]], $tree->blocks[0]->fields);
        self::assertSame(['body' => self::CLEAN, 'caption' => '<b>Kept as typed</b>'], $tree->blocks[0]->children[0]->fields);
        self::assertSame(['content' => 'not a locale map'], $tree->blocks[0]->children[1]->fields);
    }

    /**
     * @test
     *
     * @dataProvider provideBlocksSharingAKey
     */
    public function it_refuses_a_tree_with_two_blocks_sharing_a_key(StoredBlock $added): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);

        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage(sprintf('The tree has more than one block with the key %s', self::C));

        $this->apply($tree, new AddBlock(null, 1, $added));
    }

    /**
     * @return iterable<string, array{StoredBlock}>
     */
    public function provideBlocksSharingAKey(): iterable
    {
        yield 'a copy whose children were not given new keys' => [new StoredBlock(self::B, 'heading', 1, children: [
            new StoredBlock(self::C, 'heading', 1),
            new StoredBlock(self::C, 'heading', 1),
        ])];
        yield 'a copy with a child under its own key' => [new StoredBlock(self::C, 'heading', 1, children: [new StoredBlock(self::C, 'heading', 1)])];
    }

    /**
     * @test
     */
    public function it_adds_a_copy_of_a_block_with_new_keys_for_it_and_its_children(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [new StoredBlock(self::B, 'heading', 1)])]);
        $copy = new StoredBlock(self::C, 'heading', 1, children: [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d', 'heading', 1)]);

        $tree = $this->apply($tree, new AddBlock(null, 1, $copy));

        self::assertSame([self::A, self::B, self::C, '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d'], $tree->keys());
    }

    /**
     * @test
     */
    public function it_refuses_to_move_a_block_inside_one_of_its_own_children(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [new StoredBlock(self::B, 'heading', 1)])]);

        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage('inside itself');

        $this->apply($tree, new MoveBlock(self::A, self::B, 0));
    }
}
