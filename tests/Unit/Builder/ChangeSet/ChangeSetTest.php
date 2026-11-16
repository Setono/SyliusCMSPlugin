<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\AddBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\MoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\RemoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetConditions;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayout;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetShown;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetStyle;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;

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
    public function it_reads_a_blocks_visibility_conditions(): void
    {
        $changeSet = ChangeSet::fromArray(CanonicalJson::decode('{"changes":[{"op":"set_conditions","key":"' . self::KEY . '","conditions":['
            . '{"kind":"locale","negate":true,"params":{"locales":["da_DK"]}},'
            . '{"kind":"removed_kind","negate":false,"params":{}}'
            . ']},{"op":"set_conditions","key":"' . self::KEY . '","conditions":[]}]}'));

        self::assertEquals([
            new SetConditions(self::KEY, [
                ['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['da_DK']]],
                ['kind' => 'removed_kind', 'negate' => false, 'params' => []],
            ]),
            new SetConditions(self::KEY, []),
        ], $changeSet->changes);
    }

    /**
     * @test
     */
    public function it_says_why_it_rejects_a_condition_that_is_not_an_object(): void
    {
        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage('A condition must be an object');

        ChangeSet::fromArray(['changes' => [['op' => 'set_conditions', 'key' => self::KEY, 'conditions' => ['locale']]]]);
    }

    /**
     * @test
     */
    public function it_writes_a_blocks_visibility_conditions_the_way_it_reads_them(): void
    {
        $changeSet = new ChangeSet([new SetConditions(self::KEY, [
            ['kind' => 'date_window', 'negate' => false, 'params' => ['until' => '2026-12-01T00:00:00Z', 'from' => '2026-11-27T00:00:00Z']],
            ['kind' => 'signed_in', 'negate' => true, 'params' => []],
        ])]);

        $json = CanonicalJson::encode($changeSet->toArray());

        self::assertSame('{"changes":[{"op":"set_conditions","key":"' . self::KEY . '","conditions":['
            . '{"kind":"date_window","negate":false,"params":{"from":"2026-11-27T00:00:00Z","until":"2026-12-01T00:00:00Z"}},'
            . '{"kind":"signed_in","negate":true,"params":{}}'
            . ']}]}', $json);
        self::assertEquals(
            new ChangeSet([new SetConditions(self::KEY, [
                ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-11-27T00:00:00Z', 'until' => '2026-12-01T00:00:00Z']],
                ['kind' => 'signed_in', 'negate' => true, 'params' => []],
            ])]),
            ChangeSet::fromArray(CanonicalJson::decode($json)),
        );
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
     * Where a value was translated from, in a block or in the entry, or that it has no source
     *
     * @test
     */
    public function it_reads_where_values_were_translated_from(): void
    {
        $fingerprint = hash('sha256', '"Hello"');

        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'set_translated', 'key' => self::KEY, 'field' => 'text', 'locale' => 'da_DK', 'translated' => ['fingerprint' => $fingerprint, 'from' => 'en_US', 'ignored' => true]],
            ['op' => 'set_translated', 'key' => self::KEY, 'field' => 'text', 'locale' => 'de_DE', 'translated' => null],
            ['op' => 'set_entry_translated', 'field' => 'title', 'locale' => 'da_DK', 'translated' => ['from' => 'en_US', 'fingerprint' => $fingerprint]],
            ['op' => 'set_entry_translated', 'field' => 'title', 'locale' => 'de_DE', 'translated' => null],
        ]]);

        self::assertEquals([
            new SetTranslated(self::KEY, 'text', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
            new SetTranslated(self::KEY, 'text', 'de_DE', null),
            new SetEntryTranslated('title', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
            new SetEntryTranslated('title', 'de_DE', null),
        ], $changeSet->changes);
        // Only the locale and the fingerprint are kept, in that order
        $translated = $changeSet->changes[0] instanceof SetTranslated ? $changeSet->changes[0]->translated : null;
        self::assertSame(['from' => 'en_US', 'fingerprint' => $fingerprint], $translated);
    }

    /**
     * @test
     */
    public function it_writes_where_values_were_translated_from_the_way_it_reads_them(): void
    {
        $fingerprint = hash('sha256', '"Hello"');
        $changeSet = new ChangeSet([
            new SetTranslated(self::KEY, 'text', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
            new SetTranslated(self::KEY, 'text', 'de_DE', null),
            new SetEntryTranslated('title', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
            new SetEntryTranslated('title', 'de_DE', null),
        ]);

        $json = CanonicalJson::encode($changeSet->toArray());

        self::assertSame(sprintf(
            '{"changes":[{"op":"set_translated","key":"%1$s","field":"text","locale":"da_DK","translated":{"from":"en_US","fingerprint":"%2$s"}},{"op":"set_translated","key":"%1$s","field":"text","locale":"de_DE","translated":null},{"op":"set_entry_translated","field":"title","locale":"da_DK","translated":{"from":"en_US","fingerprint":"%2$s"}},{"op":"set_entry_translated","field":"title","locale":"de_DE","translated":null}]}',
            self::KEY,
            $fingerprint,
        ), $json);
        self::assertEquals($changeSet, ChangeSet::fromArray(CanonicalJson::decode($json)));
    }

    /**
     * @test
     */
    public function it_reads_a_layout_switch_and_the_layouts_values_and_translation_state(): void
    {
        $fingerprint = hash('sha256', '"Big news"');

        $changeSet = ChangeSet::fromArray(['changes' => [
            ['op' => 'set_layout', 'layout' => ['code' => 'landing', 'version' => 2, 'fields' => ['tagline' => ['en_US' => 'Big news']], 'translated' => []]],
            ['op' => 'set_layout_value', 'layout' => 'landing', 'field' => 'tagline', 'locale' => 'da_DK', 'value' => 'Nyhed'],
            ['op' => 'set_layout_value', 'layout' => 'landing', 'field' => 'transparentHeader', 'locale' => null, 'value' => true],
            ['op' => 'set_layout_translated', 'layout' => 'landing', 'field' => 'tagline', 'locale' => 'da_DK', 'translated' => ['from' => 'en_US', 'fingerprint' => $fingerprint]],
            ['op' => 'set_layout_translated', 'layout' => 'landing', 'field' => 'tagline', 'locale' => 'de_DE', 'translated' => null],
        ]]);

        self::assertEquals([
            new SetLayout(new StoredLayout('landing', 2, ['tagline' => ['en_US' => 'Big news']])),
            new SetLayoutValue('landing', 'tagline', 'da_DK', 'Nyhed'),
            new SetLayoutValue('landing', 'transparentHeader', null, true),
            new SetLayoutTranslated('landing', 'tagline', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
            new SetLayoutTranslated('landing', 'tagline', 'de_DE', null),
        ], $changeSet->changes);
    }

    /**
     * @test
     */
    public function it_writes_a_layout_switch_and_the_layouts_values_and_translation_state_the_way_it_reads_them(): void
    {
        $fingerprint = hash('sha256', '"Big news"');
        $changeSet = new ChangeSet([
            new SetLayout(new StoredLayout('landing', 2, ['tagline' => ['en_US' => 'Big news', 'da_DK' => 'Nyhed']], ['tagline' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => $fingerprint]]])),
            new SetLayout(new StoredLayout('standard', 1)),
            new SetLayoutValue('landing', 'tagline', 'da_DK', 'Nyhed'),
            new SetLayoutValue('landing', 'transparentHeader', null, null),
            new SetLayoutTranslated('landing', 'tagline', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
            new SetLayoutTranslated('landing', 'tagline', 'de_DE', null),
        ]);

        $json = CanonicalJson::encode($changeSet->toArray());

        self::assertSame(sprintf(
            '{"changes":[{"op":"set_layout","layout":{"code":"landing","version":2,"fields":{"tagline":{"da_DK":"Nyhed","en_US":"Big news"}},"translated":{"tagline":{"da_DK":{"from":"en_US","fingerprint":"%1$s"}}}}},'
            . '{"op":"set_layout","layout":{"code":"standard","version":1,"fields":{},"translated":{}}},'
            . '{"op":"set_layout_value","layout":"landing","field":"tagline","locale":"da_DK","value":"Nyhed"},'
            . '{"op":"set_layout_value","layout":"landing","field":"transparentHeader","locale":null,"value":null},'
            . '{"op":"set_layout_translated","layout":"landing","field":"tagline","locale":"da_DK","translated":{"from":"en_US","fingerprint":"%1$s"}},'
            . '{"op":"set_layout_translated","layout":"landing","field":"tagline","locale":"de_DE","translated":null}]}',
            $fingerprint,
        ), $json);
        self::assertEquals($changeSet, ChangeSet::fromArray(CanonicalJson::decode($json)));
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidTranslationStates
     */
    public function it_rejects_translation_state_without_a_locale_and_a_fingerprint(mixed $translated): void
    {
        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage('The "translated" of a change must be null, or have the locale the value was translated from and its fingerprint');

        ChangeSet::fromArray(['changes' => [['op' => 'set_entry_translated', 'field' => 'title', 'locale' => 'da_DK', 'translated' => $translated]]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function provideInvalidTranslationStates(): iterable
    {
        $fingerprint = hash('sha256', '"Hello"');

        yield 'a string' => ['en_US'];
        yield 'no locale' => [['fingerprint' => $fingerprint]];
        yield 'an empty locale' => [['from' => '', 'fingerprint' => $fingerprint]];
        yield 'a locale that is a number' => [['from' => 1, 'fingerprint' => $fingerprint]];
        yield 'a locale that is not a locale code' => [['from' => '$en_US', 'fingerprint' => $fingerprint]];
        yield 'no fingerprint' => [['from' => 'en_US']];
        yield 'a fingerprint that is a number' => [['from' => 'en_US', 'fingerprint' => 1]];
        yield 'a fingerprint that is not a SHA-256' => [['from' => 'en_US', 'fingerprint' => strtoupper($fingerprint)]];
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
        yield 'set_value with a locale that is not a locale code' => [['changes' => [['locale' => '$en_US'] + $setValue]]];
        yield 'set_entry_value with a locale that is not a locale code' => [['changes' => [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en US', 'value' => 'Om os']]]];
        yield 'set_shown with a locale that is not a locale code' => [['changes' => [['op' => 'set_shown', 'locale' => '__proto__', 'shown' => true]]]];
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
        $setTranslated = ['op' => 'set_translated', 'key' => self::KEY, 'field' => 'text', 'locale' => 'da_DK', 'translated' => null];
        yield 'set_translated without a key' => [['changes' => [array_diff_key($setTranslated, ['key' => true])]]];
        yield 'set_translated without a field' => [['changes' => [array_diff_key($setTranslated, ['field' => true])]]];
        yield 'set_translated without a locale' => [['changes' => [['locale' => null] + $setTranslated]]];
        yield 'set_translated without translated' => [['changes' => [array_diff_key($setTranslated, ['translated' => true])]]];
        yield 'set_entry_translated without a field' => [['changes' => [['op' => 'set_entry_translated', 'locale' => 'da_DK', 'translated' => null]]]];
        yield 'set_layout without a layout' => [['changes' => [['op' => 'set_layout']]]];
        yield 'set_layout with a layout without a code' => [['changes' => [['op' => 'set_layout', 'layout' => ['version' => 1]]]]];
        yield 'set_layout with a layout code that is no code' => [['changes' => [['op' => 'set_layout', 'layout' => ['code' => 'Landing', 'version' => 1]]]]];
        yield 'set_layout with a layout whose fields are a list' => [['changes' => [['op' => 'set_layout', 'layout' => ['code' => 'landing', 'version' => 1, 'fields' => [1]]]]]];
        $setLayoutValue = ['op' => 'set_layout_value', 'layout' => 'landing', 'field' => 'tagline', 'locale' => 'en_US', 'value' => 'Big news'];
        yield 'set_layout_value without a layout' => [['changes' => [array_diff_key($setLayoutValue, ['layout' => true])]]];
        yield 'set_layout_value without a field' => [['changes' => [array_diff_key($setLayoutValue, ['field' => true])]]];
        yield 'set_layout_value with a non-string locale' => [['changes' => [['locale' => 1] + $setLayoutValue]]];
        yield 'set_layout_value without a value' => [['changes' => [array_diff_key($setLayoutValue, ['value' => true])]]];
        $setLayoutTranslated = ['op' => 'set_layout_translated', 'layout' => 'landing', 'field' => 'tagline', 'locale' => 'da_DK', 'translated' => null];
        yield 'set_layout_translated without a layout' => [['changes' => [array_diff_key($setLayoutTranslated, ['layout' => true])]]];
        yield 'set_layout_translated without a field' => [['changes' => [array_diff_key($setLayoutTranslated, ['field' => true])]]];
        yield 'set_layout_translated without a locale' => [['changes' => [['locale' => null] + $setLayoutTranslated]]];
        yield 'set_layout_translated without translated' => [['changes' => [array_diff_key($setLayoutTranslated, ['translated' => true])]]];
        yield 'set_entry_translated without a locale' => [['changes' => [['op' => 'set_entry_translated', 'field' => 'title', 'translated' => null]]]];
        yield 'set_entry_translated without translated' => [['changes' => [['op' => 'set_entry_translated', 'field' => 'title', 'locale' => 'da_DK']]]];

        $condition = ['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['da_DK']]];
        $setConditions = static fn (mixed $conditions): array => ['changes' => [['op' => 'set_conditions', 'key' => self::KEY, 'conditions' => $conditions]]];
        yield 'set_conditions without a key' => [['changes' => [['op' => 'set_conditions', 'conditions' => []]]]];
        yield 'set_conditions without conditions' => [['changes' => [['op' => 'set_conditions', 'key' => self::KEY]]]];
        yield 'set_conditions with conditions as a map' => [$setConditions(['a' => $condition])];
        yield 'a condition that is not an object' => [$setConditions(['locale'])];
        yield 'a condition without a kind' => [$setConditions([array_diff_key($condition, ['kind' => true])])];
        yield 'a condition whose kind is not a code' => [$setConditions([['kind' => 'Locale'] + $condition])];
        yield 'a condition whose kind is not a string' => [$setConditions([['kind' => 1] + $condition])];
        yield 'a condition without negate' => [$setConditions([array_diff_key($condition, ['negate' => true])])];
        yield 'a condition whose negate is not a boolean' => [$setConditions([['negate' => 0] + $condition])];
        yield 'a condition without params' => [$setConditions([array_diff_key($condition, ['params' => true])])];
        yield 'a condition whose params are a list' => [$setConditions([['params' => ['da_DK']] + $condition])];
        yield 'a condition whose params are a string' => [$setConditions([['params' => 'da_DK'] + $condition])];
    }
}
