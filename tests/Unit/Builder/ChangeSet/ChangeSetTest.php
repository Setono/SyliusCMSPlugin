<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\AddBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Change;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\MoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\RemoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetShown;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetStyle;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

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
    public function it_reads_the_block_an_added_or_moved_block_goes_right_after(): void
    {
        $after = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'after' => $after, 'block' => ['key' => self::KEY, 'type' => 'heading', 'version' => 1]],
            ['op' => 'move_block', 'key' => self::KEY, 'parent' => null, 'index' => 1, 'after' => $after],
            ['op' => 'move_block', 'key' => self::KEY, 'parent' => null, 'index' => 0, 'after' => null],
        ]]);

        [$add, $move, $first] = $changeSet->changes;

        self::assertInstanceOf(AddBlock::class, $add);
        self::assertSame($after, $add->after);
        self::assertEquals(new MoveBlock(self::KEY, null, 1, $after), $move);
        self::assertEquals(new MoveBlock(self::KEY, null, 0), $first);
    }

    /**
     * @test
     */
    public function it_writes_the_changes_the_way_it_reads_them(): void
    {
        $parent = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';
        $block = new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => 'Hello']], children: [new StoredBlock($parent, 'spacer', 2)]);
        $changeSet = new ChangeSet([
            new AddBlock($parent, 2, $block, $parent),
            new MoveBlock(self::KEY, null, 0),
            new SetValue(self::KEY, 'text', 'en_US', ['type' => 'doc', 'content' => []]),
            new SetValue(self::KEY, 'level', null, null),
            new SetStyle(self::KEY, 'dark'),
            new SetStyle(self::KEY, null),
            new RemoveBlock(self::KEY),
        ]);

        $json = CanonicalJson::encode($changeSet->toArray());

        self::assertSame(
            '{"changes":[{"op":"add_block","parent":"' . $parent . '","index":2,"after":"' . $parent . '","block":{"key":"' . self::KEY . '","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[{"key":"' . $parent . '","type":"spacer","version":2,"style":null,"fields":{},"translated":{},"conditions":[],"children":[]}]}},'
            . '{"op":"move_block","key":"' . self::KEY . '","parent":null,"index":0,"after":null},'
            . '{"op":"set_value","key":"' . self::KEY . '","field":"text","locale":"en_US","value":{"content":[],"type":"doc"}},'
            . '{"op":"set_value","key":"' . self::KEY . '","field":"level","locale":null,"value":null},'
            . '{"op":"set_style","key":"' . self::KEY . '","style":"dark"},'
            . '{"op":"set_style","key":"' . self::KEY . '","style":null},'
            . '{"op":"remove_block","key":"' . self::KEY . '"}]}',
            $json,
        );
        self::assertEquals($changeSet, ChangeSet::fromArray(CanonicalJson::decode($json)));
    }

    /**
     * @test
     */
    public function it_refuses_to_write_a_change_it_does_not_know(): void
    {
        $changeSet = new ChangeSet([new RemoveBlock(self::KEY), new class() implements Change {
        }]);

        $this->expectException(\LogicException::class);

        $changeSet->toArray();
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
     * A translation's fields and its shown switch, which add the translation when the entry has none in the locale
     *
     * @test
     */
    public function it_reads_a_translations_values_and_its_shown_switch(): void
    {
        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'da_DK', 'value' => 'Om os'],
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'da_DK', 'value' => null],
            ['op' => 'set_shown', 'locale' => 'da_DK', 'shown' => true],
        ]]);

        self::assertEquals([
            new SetEntryValue('title', 'da_DK', 'Om os'),
            new SetEntryValue('slug', 'da_DK', null),
            new SetShown('da_DK', true),
        ], $changeSet->changes);
    }

    /**
     * @test
     */
    public function it_writes_a_translations_values_and_its_shown_switch_the_way_it_reads_them(): void
    {
        $changeSet = new ChangeSet([new SetEntryValue('title', 'da_DK', 'Om os'), new SetShown('da_DK', false)]);

        $json = CanonicalJson::encode($changeSet->toArray());

        self::assertSame('{"changes":[{"op":"set_entry_value","field":"title","locale":"da_DK","value":"Om os"},{"op":"set_shown","locale":"da_DK","shown":false}]}', $json);
        self::assertEquals($changeSet, ChangeSet::fromArray(CanonicalJson::decode($json)));
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
        yield 'move_block after a non-string' => [['changes' => [['after' => 1] + $moveBlock]]];
        yield 'add_block after an empty key' => [['changes' => [['after' => ''] + $addBlock]]];
        yield 'set_style without a key' => [['changes' => [['op' => 'set_style', 'style' => 'dark']]]];
        yield 'set_style without a style' => [['changes' => [['op' => 'set_style', 'key' => self::KEY]]]];
        yield 'set_style with a non-string style' => [['changes' => [['op' => 'set_style', 'key' => self::KEY, 'style' => 1]]]];
        yield 'set_style with an empty style' => [['changes' => [['op' => 'set_style', 'key' => self::KEY, 'style' => '']]]];
        yield 'set_entry_value without a field' => [['changes' => [['op' => 'set_entry_value', 'locale' => 'da_DK', 'value' => 'Om os']]]];
        yield 'set_entry_value with a non-string locale' => [['changes' => [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 1, 'value' => 'Om os']]]];
        yield 'set_entry_value without a value' => [['changes' => [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'da_DK']]]];
        yield 'set_shown without a locale' => [['changes' => [['op' => 'set_shown', 'shown' => true]]]];
        yield 'set_shown without a switch' => [['changes' => [['op' => 'set_shown', 'locale' => 'da_DK']]]];
        yield 'set_shown with a switch that is not a boolean' => [['changes' => [['op' => 'set_shown', 'locale' => 'da_DK', 'shown' => 1]]]];
    }
}
