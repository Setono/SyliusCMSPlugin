<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\AddBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Change;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetMerger;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Clash;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Merge;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\MoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\RemoveBlock;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayout;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetShown;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetStyle;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Merging a save onto what colleagues saved since the builder's lock token. See "Saving" in docs/spec/publishing.md
 */
final class ChangeSetMergerTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const E = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e';

    /**
     * @test
     */
    public function it_applies_both_when_they_change_different_fields(): void
    {
        $tree = self::tree(self::heading(self::A, ['text' => ['en_US' => 'Theirs'], 'level' => 'h2']));
        $mine = new SetValue(self::A, 'level', null, 'h3');

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Theirs')]),
            new ChangeSet([$mine]),
            $tree,
        );

        self::assertEquals([$mine], $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_applies_both_when_they_change_the_same_field_in_different_locales(): void
    {
        $tree = self::tree(self::heading(self::A, ['text' => ['en_US' => 'Hello', 'de_DE' => 'Hallo']]));
        $mine = new SetValue(self::A, 'text', 'en_US', 'Hello world');

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetValue(self::A, 'text', 'de_DE', 'Hallo')]),
            new ChangeSet([$mine]),
            $tree,
        );

        self::assertEquals([$mine], $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_applying_the_same_field_in_the_same_locale(): void
    {
        $tree = self::tree(self::heading(self::A, ['text' => ['en_US' => 'Theirs']]), self::heading(self::B));
        $other = new SetValue(self::B, 'level', null, 'h3');

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Before'), new SetValue(self::A, 'text', 'en_US', 'Theirs')]),
            new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Mine'), $other]),
            $tree,
        );

        self::assertEquals([$other], $merge->changeSet->changes);
        self::assertSame(
            [['kind' => 'value', 'key' => self::A, 'field' => 'text', 'locale' => 'en_US', 'mine' => 'Mine', 'theirs' => 'Theirs']],
            array_map(static fn (Clash $clash): array => $clash->toArray(), $merge->clashes),
        );
    }

    /**
     * @test
     */
    public function it_returns_a_clash_on_a_shared_field_with_the_value_the_colleague_set_last(): void
    {
        $tree = self::tree(self::heading(self::A));

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetValue(self::A, 'level', null, 'h4'), new SetValue(self::A, 'level', null, null)]),
            new ChangeSet([new SetValue(self::A, 'level', null, 'h3')]),
            $tree,
        );

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame(
            [['kind' => 'value', 'key' => self::A, 'field' => 'level', 'locale' => null, 'mine' => 'h3', 'theirs' => null]],
            array_map(static fn (Clash $clash): array => $clash->toArray(), $merge->clashes),
        );
    }

    /**
     * @test
     */
    public function it_applies_the_same_value_set_on_both_sides_without_a_clash(): void
    {
        $tree = self::tree(self::heading(self::A, ['text' => ['en_US' => ['type' => 'doc', 'attrs' => ['a' => 1, 'b' => 2]]]]));
        $mine = new SetValue(self::A, 'text', 'en_US', ['attrs' => ['b' => 2, 'a' => 1], 'type' => 'doc']);

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetValue(self::A, 'text', 'en_US', ['type' => 'doc', 'attrs' => ['a' => 1, 'b' => 2]])]),
            new ChangeSet([$mine]),
            $tree,
        );

        self::assertEquals([$mine], $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_applying_an_edit_to_a_block_a_colleague_deleted(): void
    {
        // A colleague deleted the group A, with the heading B inside it
        $tree = self::tree(self::heading(self::C));
        $other = new SetValue(self::C, 'level', null, 'h3');

        $merge = self::merge([new RemoveBlock(self::A)], [
            new SetValue(self::B, 'text', 'en_US', 'Mine'),
            new SetValue(self::B, 'level', null, 'h4'),
            $other,
            new MoveBlock(self::A, null, 1),
        ], $tree);

        self::assertEquals([$other], $merge->changeSet->changes);
        self::assertSame([
            ['kind' => 'deleted_block', 'key' => self::B],
            ['kind' => 'deleted_block', 'key' => self::A],
        ], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_adding_or_moving_a_block_into_a_block_a_colleague_deleted(): void
    {
        $tree = self::tree(self::heading(self::C));

        $merge = self::merge([new RemoveBlock(self::A)], [
            new AddBlock(self::A, 0, self::heading(self::B, children: [self::heading(self::D)])),
            new SetValue(self::D, 'level', null, 'h3'),
            new MoveBlock(self::C, self::A, 0),
        ], $tree);

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([['kind' => 'deleted_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_applies_edits_to_blocks_the_editor_added_in_the_same_save(): void
    {
        $tree = self::tree(self::heading(self::A));
        $mine = [
            new AddBlock(self::A, 0, self::heading(self::B, children: [self::heading(self::C)])),
            new SetValue(self::C, 'level', null, 'h3'),
            new MoveBlock(self::C, null, 0),
            new SetValue(self::B, 'level', null, 'h4'),
        ];

        $merge = self::merge([new SetValue(self::A, 'level', null, 'h2')], $mine, $tree);

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_drops_the_removal_of_a_block_a_colleague_deleted_too(): void
    {
        $tree = self::tree(self::heading(self::C));

        $merge = self::merge([new RemoveBlock(self::A)], [new RemoveBlock(self::B)], $tree);

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_a_colleague_changed_something_in(): void
    {
        $tree = self::tree(
            self::heading(self::A, children: [self::heading(self::B)]),
            self::heading(self::C, children: [self::heading(self::D)]),
        );

        // A colleague changed B, inside A, and added D into C
        $merge = self::merge(
            [new SetValue(self::B, 'level', null, 'h3'), new AddBlock(self::C, 0, self::heading(self::D))],
            [new RemoveBlock(self::A), new RemoveBlock(self::C)],
            $tree,
        );

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([
            ['kind' => 'changed_block', 'key' => self::A],
            ['kind' => 'changed_block', 'key' => self::C],
        ], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_a_colleague_moved_another_block_into(): void
    {
        $tree = self::tree(self::heading(self::A, children: [self::heading(self::B)]));

        $merge = self::merge([new MoveBlock(self::B, self::A, 0)], [new RemoveBlock(self::A)], $tree);

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([['kind' => 'changed_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_the_editor_moved_a_block_a_colleague_changed_into(): void
    {
        $tree = self::tree(self::heading(self::A), self::heading(self::B));

        $merge = self::merge([new SetValue(self::B, 'level', null, 'h3')], [new MoveBlock(self::B, self::A, 0), new RemoveBlock(self::A)], $tree);

        self::assertEquals([new MoveBlock(self::B, self::A, 0)], $merge->changeSet->changes);
        self::assertSame([['kind' => 'changed_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_applies_moves_on_both_sides(): void
    {
        $tree = self::tree(self::heading(self::B, children: [self::heading(self::C)]), self::heading(self::A), self::heading(self::D));
        $mine = [
            new MoveBlock(self::A, self::B, 1),
            new SetValue(self::C, 'level', null, 'h3'),
            new MoveBlock(self::D, null, 0),
            new RemoveBlock(self::B),
        ];

        // A colleague moved C into B, D to the end and B itself, and changed A
        $merge = self::merge(
            [new MoveBlock(self::C, self::B, 0), new MoveBlock(self::D, null, 2), new MoveBlock(self::B, null, 0), new SetValue(self::A, 'level', null, 'h2')],
            [...$mine],
            $tree,
        );

        self::assertEquals([$mine[0], $mine[1], $mine[2]], $merge->changeSet->changes);
        self::assertSame([['kind' => 'changed_block', 'key' => self::B]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_applies_a_move_of_a_block_only_the_colleague_moved_or_removing_it(): void
    {
        $tree = self::tree(self::heading(self::A, children: [self::heading(self::B)]), self::heading(self::C));
        $mine = [new MoveBlock(self::A, null, 1), new RemoveBlock(self::A)];

        $merge = self::merge([new MoveBlock(self::A, null, 0), new MoveBlock(self::C, null, 0)], $mine, $tree);

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_one_clash_for_each_block_a_colleague_deleted_that_the_editor_added_blocks_into(): void
    {
        $tree = self::tree(self::heading(self::C));

        $merge = self::merge([new RemoveBlock(self::A), new RemoveBlock(self::B)], [
            new AddBlock(self::A, 0, self::heading(self::D)),
            new AddBlock(self::B, 0, self::heading(self::E)),
            new SetValue(self::D, 'level', null, 'h3'),
            new SetValue(self::E, 'level', null, 'h3'),
        ], $tree);

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([
            ['kind' => 'deleted_block', 'key' => self::A],
            ['kind' => 'deleted_block', 'key' => self::B],
        ], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_applies_a_move_into_a_block_the_editor_added_in_the_same_save(): void
    {
        $tree = self::tree(self::heading(self::A));
        $mine = [new AddBlock(null, 1, self::heading(self::B, children: [self::heading(self::C)]), self::A), new MoveBlock(self::A, self::C, 0)];

        $merge = self::merge([new SetValue(self::A, 'level', null, 'h2')], $mine, $tree);

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_passes_a_move_into_the_block_itself_on_for_the_applier_to_refuse_and_keeps_finding_the_block(): void
    {
        $tree = self::tree(self::heading(self::A, children: [self::heading(self::B)]));
        $mine = [new MoveBlock(self::A, self::B, 0), new SetValue(self::A, 'level', null, 'h3')];

        $merge = self::merge([new SetValue(self::B, 'level', null, 'h2')], $mine, $tree);

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_applies_the_removal_of_a_block_a_colleague_changed_nothing_in(): void
    {
        $tree = self::tree(self::heading(self::A, children: [self::heading(self::B)]), self::heading(self::C));
        $mine = [new RemoveBlock(self::A)];

        $merge = self::merge([new SetValue(self::C, 'level', null, 'h2'), new AddBlock(null, 0, self::heading(self::D))], $mine, $tree);

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_whose_first_child_a_colleague_changed(): void
    {
        $tree = self::tree(self::heading(self::A, children: [self::heading(self::B), self::heading(self::C)]));

        $merge = self::merge([new SetValue(self::B, 'level', null, 'h2')], [new RemoveBlock(self::A)], $tree);

        self::assertSame([['kind' => 'changed_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_with_a_change_deep_inside_it(): void
    {
        $tree = self::tree(self::heading(self::A, children: [self::heading(self::B, children: [self::heading(self::C, children: [self::heading(self::D)])])]));

        $merge = self::merge([new SetValue(self::D, 'level', null, 'h2')], [new RemoveBlock(self::A)], $tree);

        self::assertSame([['kind' => 'changed_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_applies_the_style_the_editor_picked_last_when_both_picked_one(): void
    {
        $mine = [new SetStyle(self::A, 'light')];

        $merge = self::merge([new SetStyle(self::A, 'dark')], $mine, self::tree(self::heading(self::A)));

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_picking_a_style_for_a_block_a_colleague_deleted(): void
    {
        $merge = self::merge([new RemoveBlock(self::A)], [new SetStyle(self::A, 'dark')], self::tree(self::heading(self::B)));

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([['kind' => 'deleted_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_a_colleague_picked_a_style_for(): void
    {
        $merge = self::merge([new SetStyle(self::B, 'dark')], [new RemoveBlock(self::A)], self::tree(self::heading(self::A, children: [self::heading(self::B)])));

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([['kind' => 'changed_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_applies_entry_values_set_in_another_field_or_another_locale(): void
    {
        $mine = [new SetEntryValue('title', 'da_DK', 'Om os'), new SetEntryValue('slug', 'en_US', 'about')];

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetEntryValue('title', 'en_US', 'About us'), new SetEntryValue('slug', 'da_DK', 'om-os')]),
            new ChangeSet($mine),
            self::tree(),
        );

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_applying_an_entry_value_a_colleague_set_in_the_same_locale(): void
    {
        $same = new SetEntryValue('slug', 'da_DK', 'om-os');

        $merge = (new ChangeSetMerger())->merge(
            new ChangeSet([new SetEntryValue('title', 'da_DK', 'Om'), new SetEntryValue('title', 'da_DK', 'Om dem'), $same]),
            new ChangeSet([new SetEntryValue('title', 'da_DK', 'Om os'), $same]),
            self::tree(),
        );

        self::assertEquals([$same], $merge->changeSet->changes);
        self::assertSame(
            [['kind' => 'entry_value', 'field' => 'title', 'locale' => 'da_DK', 'mine' => 'Om os', 'theirs' => 'Om dem']],
            array_map(static fn (Clash $clash): array => $clash->toArray(), $merge->clashes),
        );
    }

    /**
     * A switch is no text to lose: the editor's last wins, as for a block style
     *
     * @test
     */
    public function it_applies_the_shown_switch_the_editor_set_last_when_both_set_it(): void
    {
        $mine = new SetShown('da_DK', true);

        $merge = (new ChangeSetMerger())->merge(new ChangeSet([new SetShown('da_DK', false)]), new ChangeSet([$mine]), self::tree());

        self::assertEquals([$mine], $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * Translation state isn't a value an editor types, so the editor's last wins, as for a style
     *
     * @test
     */
    public function it_applies_the_translation_state_the_editor_set_last_when_both_set_it(): void
    {
        $source = ['from' => 'en_US', 'fingerprint' => hash('sha256', '"Hello"')];
        $mine = [new SetTranslated(self::A, 'text', 'da_DK', $source), new SetEntryTranslated('title', 'da_DK', $source)];

        $merge = self::merge(
            [new SetTranslated(self::A, 'text', 'da_DK', null), new SetEntryTranslated('title', 'da_DK', null)],
            $mine,
            self::tree(self::heading(self::A)),
        );

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_setting_the_translation_state_of_a_block_a_colleague_deleted(): void
    {
        $merge = self::merge([new RemoveBlock(self::A)], [new SetTranslated(self::A, 'text', 'da_DK', null)], self::tree(self::heading(self::B)));

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([['kind' => 'deleted_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * @test
     */
    public function it_returns_a_clash_instead_of_deleting_a_block_a_colleague_set_the_translation_state_in(): void
    {
        $merge = self::merge([new SetTranslated(self::B, 'text', 'da_DK', null)], [new RemoveBlock(self::A)], self::tree(self::heading(self::A, children: [self::heading(self::B)])));

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([['kind' => 'changed_block', 'key' => self::A]], self::clashes($merge));
    }

    /**
     * A layout is a presentation choice, not text to lose: the editor's last wins, as for a block style, and so does a
     * layout field's value, which presentation options are
     *
     * @test
     */
    public function it_applies_the_layout_and_the_layout_values_the_editor_set_last_when_both_set_them(): void
    {
        $mine = [
            new SetLayout(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Mine']])),
            new SetLayoutValue('landing', 'tagline', 'da_DK', 'Min'),
            new SetLayoutTranslated('landing', 'tagline', 'da_DK', null),
        ];

        $merge = self::merge(
            [new SetLayout(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Theirs']])), new SetLayoutValue('landing', 'tagline', 'da_DK', 'Deres')],
            $mine,
            new StoredTree(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Theirs', 'da_DK' => 'Deres']]), []),
        );

        self::assertEquals($mine, $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * A switch drops the values the new layout has no field for, so a value set in the layout before is dropped too
     *
     * @test
     */
    public function it_leaves_out_a_layout_value_set_in_a_layout_a_colleague_switched_away_from(): void
    {
        $merge = self::merge(
            [new SetLayout(new StoredLayout('landing', 1))],
            [new SetLayoutValue('standard', 'showTitle', null, false), new SetLayoutTranslated('standard', 'tagline', 'da_DK', null)],
            new StoredTree(new StoredLayout('landing', 1), []),
        );

        self::assertSame([], $merge->changeSet->changes);
        self::assertSame([], $merge->clashes);
    }

    /**
     * @test
     */
    public function it_applies_the_layout_values_the_editor_set_in_the_layout_they_switched_to(): void
    {
        $mine = [new SetLayout(new StoredLayout('standard', 1)), new SetLayoutValue('standard', 'showTitle', null, false)];

        $merge = self::merge([new SetLayout(new StoredLayout('landing', 1))], $mine, new StoredTree(new StoredLayout('landing', 1), []));

        self::assertEquals($mine, $merge->changeSet->changes);
    }

    /**
     * @test
     */
    public function it_leaves_out_a_layout_value_of_a_tree_without_a_layout(): void
    {
        $merge = self::merge([], [new SetLayoutValue('standard', 'showTitle', null, false)], self::tree());

        self::assertSame([], $merge->changeSet->changes);
    }

    /**
     * @test
     */
    public function it_passes_a_change_it_does_not_know_on_for_the_applier_to_refuse(): void
    {
        $unknown = new class() implements Change {
        };

        $merge = self::merge([new SetValue(self::A, 'level', null, 'h2')], [$unknown], self::tree(self::heading(self::A)));

        self::assertSame([$unknown], $merge->changeSet->changes);
    }

    /**
     * @param list<Change> $theirs
     * @param list<Change> $mine
     */
    private static function merge(array $theirs, array $mine, StoredTree $tree): Merge
    {
        return (new ChangeSetMerger())->merge(new ChangeSet($theirs), new ChangeSet($mine), $tree);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function clashes(Merge $merge): array
    {
        return array_map(static fn (Clash $clash): array => $clash->toArray(), $merge->clashes);
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<StoredBlock> $children
     */
    private static function heading(string $key, array $fields = [], array $children = []): StoredBlock
    {
        return new StoredBlock($key, 'heading', 1, fields: $fields, children: $children);
    }

    private static function tree(StoredBlock ...$blocks): StoredTree
    {
        return new StoredTree(null, array_values($blocks));
    }
}
