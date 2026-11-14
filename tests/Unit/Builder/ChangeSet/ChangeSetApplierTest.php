<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\AddBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Change;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\RemoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

final class ChangeSetApplierTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

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

        (new ChangeSetApplier(new BlockTypeRegistry(['heading' => Heading::class], new FieldReader())))->apply(
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
    public function it_refuses_a_change_that_does_not_fit_the_tree(AddBlock|RemoveBlock|SetValue $change, string $message): void
    {
        $existing = new StoredBlock(self::A, 'heading', 1);
        $unknown = new StoredBlock(self::B, 'removed', 1);
        $tree = new StoredTree(null, [$existing, $unknown]);

        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage($message);

        $this->apply($tree, $change);
    }

    /**
     * @return iterable<string, array{AddBlock|RemoveBlock|SetValue, string}>
     */
    public function provideInvalidChanges(): iterable
    {
        $other = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';
        $child = new StoredBlock($other, 'heading', 1);
        $child->children = [new StoredBlock(self::A, 'heading', 1)];
        $withUnknownChild = new StoredBlock($other, 'heading', 1);
        $withUnknownChild->children = [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d', 'nope', 1)];

        yield 'adding a block with a key the tree has' => [new AddBlock(null, 0, new StoredBlock(self::A, 'heading', 1)), self::A];
        yield 'adding a block whose child has a key the tree has' => [new AddBlock(null, 0, $child), self::A];
        yield 'adding a block of an unknown type' => [new AddBlock(null, 0, new StoredBlock($other, 'nope', 1)), '"nope"'];
        yield 'adding a block with a child of an unknown type' => [new AddBlock(null, 0, $withUnknownChild), '"nope"'];
        yield 'adding a block inside another' => [new AddBlock(self::A, 0, new StoredBlock($other, 'heading', 1)), 'top level'];
        yield 'removing a block the tree does not have' => [new RemoveBlock($other), $other];
        yield 'setting a value on a block the tree does not have' => [new SetValue($other, 'text', 'en_US', 'Hello'), $other];
        yield 'setting a value on a block of an unknown type' => [new SetValue(self::B, 'text', 'en_US', 'Hello'), '"removed"'];
        yield 'setting a field the block type does not have' => [new SetValue(self::A, 'subtitle', 'en_US', 'Hello'), '"subtitle"'];
        yield 'setting a translatable field without a locale' => [new SetValue(self::A, 'text', null, 'Hello'), 'translatable'];
        yield 'setting a shared field for one locale' => [new SetValue(self::A, 'level', 'en_US', 'h3'), 'shared'];
    }

    private function apply(StoredTree $tree, AddBlock|RemoveBlock|SetValue ...$changes): StoredTree
    {
        return (new ChangeSetApplier(new BlockTypeRegistry(['heading' => Heading::class], new FieldReader())))->apply(new ChangeSet(array_values($changes)), $tree);
    }
}
