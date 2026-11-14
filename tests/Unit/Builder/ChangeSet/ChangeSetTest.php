<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\AddBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\MoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\RemoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetStyle;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;

final class ChangeSetTest extends TestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_reads_the_changes_in_order(): void
    {
        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::KEY, 'type' => 'heading', 'version' => 1]],
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Hello'],
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'level', 'locale' => null, 'value' => null],
            ['op' => 'remove_block', 'key' => self::KEY],
        ]]);

        self::assertCount(4, $changeSet->changes);

        [$add, $setText, $setLevel, $remove] = $changeSet->changes;

        self::assertInstanceOf(AddBlock::class, $add);
        self::assertNull($add->parent);
        self::assertSame(0, $add->index);
        self::assertSame(self::KEY, $add->block->key);
        self::assertSame('heading', $add->block->type);

        self::assertInstanceOf(SetValue::class, $setText);
        self::assertSame(self::KEY, $setText->key);
        self::assertSame('text', $setText->field);
        self::assertSame('en_US', $setText->locale);
        self::assertSame('Hello', $setText->value);

        self::assertInstanceOf(SetValue::class, $setLevel);
        self::assertNull($setLevel->locale);
        self::assertNull($setLevel->value);

        self::assertInstanceOf(RemoveBlock::class, $remove);
        self::assertSame(self::KEY, $remove->key);
    }

    /**
     * @test
     */
    public function it_reads_a_move_into_a_parent_and_to_the_top_level(): void
    {
        $parent = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'move_block', 'key' => self::KEY, 'parent' => $parent, 'index' => 2],
            ['op' => 'move_block', 'key' => self::KEY, 'parent' => null, 'index' => 0],
        ]]);

        [$into, $out] = $changeSet->changes;

        self::assertEquals(new MoveBlock(self::KEY, $parent, 2), $into);
        self::assertEquals(new MoveBlock(self::KEY, null, 0), $out);
    }

    /**
     * @test
     */
    public function it_reads_a_block_style_picked_and_one_taken_away(): void
    {
        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'set_style', 'key' => self::KEY, 'style' => 'dark'],
            ['op' => 'set_style', 'key' => self::KEY, 'style' => null],
        ]]);

        self::assertEquals([new SetStyle(self::KEY, 'dark'), new SetStyle(self::KEY, null)], $changeSet->changes);
    }

    /**
     * @test
     */
    public function it_reads_an_empty_change_set(): void
    {
        self::assertSame([], ChangeSet::fromArray(['changes' => []])->changes);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidChangeSets
     */
    public function it_rejects_an_invalid_change_set(mixed $data): void
    {
        $this->expectException(InvalidChangeSetException::class);

        ChangeSet::fromArray($data);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function provideInvalidChangeSets(): iterable
    {
        $setValue = ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Hello'];
        $addBlock = ['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::KEY, 'type' => 'heading', 'version' => 1]];
        $moveBlock = ['op' => 'move_block', 'key' => self::KEY, 'parent' => null, 'index' => 0];

        yield 'not an object' => ['changes'];
        yield 'no changes' => [[]];
        yield 'changes as a map' => [['changes' => ['a' => $setValue]]];
        yield 'a change that is not an object' => [['changes' => ['set_value']]];
        yield 'a change that is an empty object' => [['changes' => [new \stdClass()]]];
        yield 'an unknown op' => [['changes' => [['op' => 'copy_block'] + $setValue]]];
        yield 'no op' => [['changes' => [array_diff_key($setValue, ['op' => true])]]];
        yield 'set_value without a key' => [['changes' => [array_diff_key($setValue, ['key' => true])]]];
        yield 'set_value without a field' => [['changes' => [['field' => 1] + $setValue]]];
        yield 'set_value with an empty field' => [['changes' => [['field' => ''] + $setValue]]];
        yield 'set_value with a non-string locale' => [['changes' => [['locale' => 1] + $setValue]]];
        yield 'set_value with an empty locale' => [['changes' => [['locale' => ''] + $setValue]]];
        yield 'set_value without a value' => [['changes' => [array_diff_key($setValue, ['value' => true])]]];
        yield 'remove_block without a key' => [['changes' => [['op' => 'remove_block']]]];
        yield 'add_block with a negative index' => [['changes' => [['index' => -1] + $addBlock]]];
        yield 'add_block without an index' => [['changes' => [array_diff_key($addBlock, ['index' => true])]]];
        yield 'add_block with a non-string parent' => [['changes' => [['parent' => 1] + $addBlock]]];
        yield 'add_block with an invalid block' => [['changes' => [['block' => ['type' => 'heading']] + $addBlock]]];
        yield 'move_block without a key' => [['changes' => [array_diff_key($moveBlock, ['key' => true])]]];
        yield 'move_block with a non-string parent' => [['changes' => [['parent' => 1] + $moveBlock]]];
        yield 'move_block without an index' => [['changes' => [array_diff_key($moveBlock, ['index' => true])]]];
        yield 'move_block with a negative index' => [['changes' => [['index' => -1] + $moveBlock]]];
        yield 'set_style without a key' => [['changes' => [['op' => 'set_style', 'style' => 'dark']]]];
        yield 'set_style without a style' => [['changes' => [['op' => 'set_style', 'key' => self::KEY]]]];
        yield 'set_style with a non-string style' => [['changes' => [['op' => 'set_style', 'key' => self::KEY, 'style' => 1]]]];
        yield 'set_style with an empty style' => [['changes' => [['op' => 'set_style', 'key' => self::KEY, 'style' => '']]]];
    }
}
