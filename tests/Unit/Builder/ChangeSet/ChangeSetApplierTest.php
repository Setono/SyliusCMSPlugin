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
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet\Fixture\Banner;
use Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet\Fixture\SharedNote;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
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
    public function it_replaces_a_blocks_visibility_conditions_of_any_block_type(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'group', 1), new StoredBlock(self::B, 'removed', 1)]);
        $conditions = [
            ['kind' => 'locale_is', 'negate' => true, 'params' => ['locales' => ['da_DK']]],
            ['kind' => 'signed_in', 'negate' => false, 'params' => []],
        ];

        $tree = $this->apply($tree, new SetConditions(self::A, $conditions), new SetConditions(self::B, $conditions));
        self::assertSame($conditions, $tree->blocks[0]->conditions);
        self::assertSame($conditions, $tree->blocks[1]->conditions, 'A block of a type the code no longer has keeps its conditions too');

        $tree = $this->apply($tree, new SetConditions(self::A, []));
        self::assertSame([], $tree->blocks[0]->conditions);
    }

    /**
     * @test
     */
    public function it_sanitises_the_parameter_values_of_a_condition_by_their_field_types(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'group', 1)]);

        $tree = $this->apply($tree, new SetConditions(self::A, [
            ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-11-27T01:00:00+01:00', 'until' => null, 'unknown' => '2026-11-27T01:00:00+01:00']],
            ['kind' => 'removed_kind', 'negate' => false, 'params' => ['from' => '2026-11-27T01:00:00+01:00']],
        ]));

        self::assertSame([
            ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-11-27T00:00:00Z', 'until' => null, 'unknown' => '2026-11-27T01:00:00+01:00']],
            ['kind' => 'removed_kind', 'negate' => false, 'params' => ['from' => '2026-11-27T01:00:00+01:00']],
        ], $tree->blocks[0]->conditions, 'A kind the code no longer has, and a parameter its kind doesn\'t have, are kept as they are');
    }

    /**
     * @test
     */
    public function it_refuses_conditions_for_a_block_the_tree_does_not_have(): void
    {
        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage(sprintf('The tree has no block with the key %s', self::B));

        $this->apply(new StoredTree(null, [new StoredBlock(self::A, 'group', 1)]), new SetConditions(self::B, []));
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
     *
     * @dataProvider provideChangeSetsWithAnInvalidChange
     */
    public function it_applies_nothing_when_one_change_is_invalid(Change ...$changes): void
    {
        $page = self::page(new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]));

        try {
            self::applier()->apply(new ChangeSet(array_values($changes)), $page);
            self::fail('The change set must be refused');
        } catch (InvalidChangeSetException) {
        }

        self::assertSame([], $page->getBlockTree()->blocks[0]->fields);
        self::assertSame('About us', $page->getTranslation('en_US')->getTitle());
        self::assertSame(['en_US'], $page->getTranslations()->getKeys());
        self::assertFalse($page->isNoindex());
    }

    /**
     * @return iterable<string, list<Change>>
     */
    public function provideChangeSetsWithAnInvalidChange(): iterable
    {
        yield 'a block change after valid ones' => [new SetValue(self::A, 'text', 'en_US', 'Hello'), new SetEntryValue('title', 'en_US', 'About'), new RemoveBlock(self::B)];
        yield 'an entry change after valid ones' => [new SetValue(self::A, 'text', 'en_US', 'Hello'), new SetEntryValue('title', 'da_DK', 'Om os'), new SetEntryValue('title', 'en_US', 1)];
        yield 'a shared entry change before an invalid one' => [new SetEntryValue('noindex', null, true), new SetEntryValue('title', 'en_US', 1)];
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
            self::page(new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)])),
        );
    }

    /**
     * @test
     */
    public function it_sets_and_removes_a_translations_title_and_slug(): void
    {
        $page = self::page(new StoredTree(null, []));

        self::applier()->apply(new ChangeSet([new SetEntryValue('title', 'en_US', 'About'), new SetEntryValue('slug', 'en_US', 'about')]), $page);

        self::assertSame('About', $page->getTranslation('en_US')->getTitle());
        self::assertSame('about', $page->getTranslation('en_US')->getSlug());

        self::applier()->apply(new ChangeSet([new SetEntryValue('title', 'en_US', ''), new SetEntryValue('slug', 'en_US', null)]), $page);

        self::assertNull($page->getTranslation('en_US')->getTitle());
        self::assertNull($page->getTranslation('en_US')->getSlug());
    }

    /**
     * See "SEO fields" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_sets_and_removes_a_translations_meta_title_and_meta_description(): void
    {
        $page = self::page(new StoredTree(null, []));

        self::applier()->apply(new ChangeSet([new SetEntryValue('metaTitle', 'en_US', 'About us | Shop'), new SetEntryValue('metaDescription', 'en_US', 'All about us')]), $page);

        self::assertSame('About us | Shop', $page->getTranslation('en_US')->getMetaTitle());
        self::assertSame('All about us', $page->getTranslation('en_US')->getMetaDescription());

        self::applier()->apply(new ChangeSet([new SetEntryValue('metaTitle', 'en_US', ''), new SetEntryValue('metaDescription', 'en_US', null)]), $page);

        self::assertNull($page->getTranslation('en_US')->getMetaTitle());
        self::assertNull($page->getTranslation('en_US')->getMetaDescription());
        self::assertSame('About us', $page->getTranslation('en_US')->getTitle());
        self::assertSame('about-us', $page->getTranslation('en_US')->getSlug());
    }

    /**
     * "Hide from search engines" is shared by all translations
     *
     * @test
     */
    public function it_switches_hide_from_search_engines_on_and_off(): void
    {
        $page = self::page(new StoredTree(null, []));

        self::applier()->apply(new ChangeSet([new SetEntryValue('noindex', null, true)]), $page);
        self::assertTrue($page->isNoindex());
        self::assertSame(['en_US'], $page->getTranslations()->getKeys());

        self::applier()->apply(new ChangeSet([new SetEntryValue('noindex', null, false)]), $page);
        self::assertFalse($page->isNoindex());

        $page->setNoindex(true);
        self::applier()->apply(new ChangeSet([new SetEntryValue('noindex', null, null)]), $page);
        self::assertFalse($page->isNoindex(), 'Removing the value switches it off');
    }

    /**
     * Translations made before the first publish start shown. See "Held-back translations" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_adds_a_translation_that_starts_shown_to_an_entry_that_was_never_published(): void
    {
        $page = self::page(new StoredTree(null, []));

        self::applier()->apply(new ChangeSet([new SetEntryValue('title', 'da_DK', 'Om os'), new SetEntryValue('slug', 'da_DK', 'om-os')]), $page);

        $danish = $page->getTranslations()->get('da_DK');
        self::assertInstanceOf(PageTranslation::class, $danish);
        self::assertSame('Om os', $danish->getTitle());
        self::assertSame('om-os', $danish->getSlug());
        self::assertTrue($danish->isShown());
        self::assertSame($page, $danish->getTranslatable());
        self::assertSame($danish, $page->getTranslation('da_DK'));
    }

    /**
     * A translation added after the first publish starts held back
     *
     * @test
     */
    public function it_adds_a_translation_that_starts_held_back_to_an_entry_that_has_been_published(): void
    {
        $page = self::page(new StoredTree(null, []));
        $page->publish(new \DateTimeImmutable());

        self::applier()->apply(new ChangeSet([new SetEntryValue('title', 'da_DK', 'Om os')]), $page);

        self::assertFalse($page->getTranslation('da_DK')->isShown());
        self::assertTrue($page->getTranslation('en_US')->isShown());
    }

    /**
     * @test
     */
    public function it_switches_a_translation_shown_and_held_back(): void
    {
        $page = self::page(new StoredTree(null, []));

        self::applier()->apply(new ChangeSet([new SetShown('en_US', false)]), $page);
        self::assertFalse($page->getTranslation('en_US')->isShown());

        self::applier()->apply(new ChangeSet([new SetShown('en_US', true)]), $page);
        self::assertTrue($page->getTranslation('en_US')->isShown());
    }

    /**
     * @test
     */
    public function it_adds_a_translation_switched_the_way_a_shown_change_says(): void
    {
        $page = self::page(new StoredTree(null, []));
        $page->publish(new \DateTimeImmutable());

        self::applier()->apply(new ChangeSet([new SetShown('da_DK', true), new SetEntryValue('title', 'da_DK', 'Om os')]), $page);

        self::assertTrue($page->getTranslation('da_DK')->isShown());
        self::assertSame('Om os', $page->getTranslation('da_DK')->getTitle());
    }

    /**
     * @test
     */
    public function it_sets_and_removes_where_a_translations_values_were_translated_from(): void
    {
        $page = self::page(new StoredTree(null, []));
        $page->getTranslation('en_US')->setTranslated(['slug' => ['from' => 'da_DK', 'fingerprint' => self::fingerprint('om-os')]]);

        self::applier()->apply(new ChangeSet([new SetEntryTranslated('title', 'en_US', ['from' => 'da_DK', 'fingerprint' => self::fingerprint('Om os')])]), $page);

        self::assertSame([
            'slug' => ['from' => 'da_DK', 'fingerprint' => self::fingerprint('om-os')],
            'title' => ['from' => 'da_DK', 'fingerprint' => self::fingerprint('Om os')],
        ], $page->getTranslation('en_US')->getTranslated());

        self::applier()->apply(new ChangeSet([new SetEntryTranslated('slug', 'en_US', null)]), $page);

        self::assertSame(['title' => ['from' => 'da_DK', 'fingerprint' => self::fingerprint('Om os')]], $page->getTranslation('en_US')->getTranslated());
    }

    /**
     * @test
     */
    public function it_adds_a_translation_with_where_its_value_was_translated_from(): void
    {
        $page = self::page(new StoredTree(null, []));

        self::applier()->apply(new ChangeSet([
            new SetEntryValue('title', 'da_DK', 'Om os'),
            new SetEntryTranslated('title', 'da_DK', ['from' => 'en_US', 'fingerprint' => self::fingerprint('About us')]),
        ]), $page);

        self::assertSame('Om os', $page->getTranslation('da_DK')->getTitle());
        self::assertSame(['title' => ['from' => 'en_US', 'fingerprint' => self::fingerprint('About us')]], $page->getTranslation('da_DK')->getTranslated());
    }

    /**
     * @test
     */
    public function it_sets_and_removes_where_a_block_s_values_were_translated_from(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 'Hej', 'de_DE' => 'Hallo']])]);

        $tree = $this->apply(
            $tree,
            new SetTranslated(self::A, 'text', 'da_DK', ['from' => 'en_US', 'fingerprint' => self::fingerprint('Hello')]),
            new SetTranslated(self::A, 'text', 'de_DE', ['from' => 'en_US', 'fingerprint' => self::fingerprint('Hello')]),
        );

        self::assertSame(['text' => [
            'da_DK' => ['from' => 'en_US', 'fingerprint' => self::fingerprint('Hello')],
            'de_DE' => ['from' => 'en_US', 'fingerprint' => self::fingerprint('Hello')],
        ]], $tree->blocks[0]->translated);

        $tree = $this->apply($tree, new SetTranslated(self::A, 'text', 'da_DK', null));

        self::assertSame(['text' => ['de_DE' => ['from' => 'en_US', 'fingerprint' => self::fingerprint('Hello')]]], $tree->blocks[0]->translated);

        $tree = $this->apply($tree, new SetTranslated(self::A, 'text', 'de_DE', null));

        self::assertSame([], $tree->blocks[0]->translated);
    }

    /**
     * @test
     */
    public function it_switches_the_layout_to_the_one_the_builder_made_and_leaves_the_blocks_as_they_are(): void
    {
        $block = new StoredBlock(self::A, 'heading', 1);
        $layout = new StoredLayout('banner', 3, ['tagline' => ['en_US' => 'Big news'], 'dark' => true], ['tagline' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => self::fingerprint('Big news')]]]);

        $tree = $this->apply(new StoredTree(new StoredLayout('standard', 1, ['showTitle' => false]), [$block]), new SetLayout($layout));

        self::assertEquals($layout, $tree->layout);
        self::assertNotSame($layout, $tree->layout, 'The change keeps its own layout');
        self::assertEquals([$block], $tree->blocks);
    }

    /**
     * @test
     */
    public function it_sanitises_the_rich_text_values_of_a_layout_switched_to(): void
    {
        $tree = $this->apply(new StoredTree(new StoredLayout('standard', 1), []), new SetLayout(new StoredLayout('banner', 3, ['intro' => ['en_US' => self::DIRTY, 'da_DK' => self::DIRTY]])));

        self::assertSame(['intro' => ['en_US' => self::CLEAN, 'da_DK' => self::CLEAN]], $tree->layout?->fields);
    }

    /**
     * @test
     */
    public function it_keeps_a_layout_the_code_no_longer_has_as_it_is_which_undoing_a_switch_from_it_brings_back(): void
    {
        $removed = new StoredLayout('gone', 4, ['anything' => ['en_US' => self::DIRTY]]);

        $tree = $this->apply(new StoredTree(new StoredLayout('standard', 1), []), new SetLayout($removed));

        self::assertEquals($removed, $tree->layout);
    }

    /**
     * @test
     */
    public function it_sets_and_removes_the_layouts_values_by_field_and_locale_sanitising_rich_text(): void
    {
        $tree = $this->apply(
            new StoredTree(new StoredLayout('banner', 3, ['tagline' => ['en_US' => 'Big news']]), []),
            new SetLayoutValue('banner', 'tagline', 'da_DK', 'Nyhed'),
            new SetLayoutValue('banner', 'dark', null, true),
            new SetLayoutValue('banner', 'intro', 'en_US', self::DIRTY),
        );

        self::assertSame(['tagline' => ['en_US' => 'Big news', 'da_DK' => 'Nyhed'], 'dark' => true, 'intro' => ['en_US' => self::CLEAN]], $tree->layout?->fields);

        $tree = $this->apply($tree, new SetLayoutValue('banner', 'tagline', 'en_US', null), new SetLayoutValue('banner', 'dark', null, null), new SetLayoutValue('banner', 'intro', 'en_US', null));

        self::assertSame(['tagline' => ['da_DK' => 'Nyhed']], $tree->layout?->fields);
    }

    /**
     * @test
     */
    public function it_sets_and_removes_where_the_layouts_values_were_translated_from(): void
    {
        $source = ['from' => 'en_US', 'fingerprint' => self::fingerprint('Big news')];

        $tree = $this->apply(new StoredTree(new StoredLayout('banner', 3, ['tagline' => ['en_US' => 'Big news', 'da_DK' => 'Nyhed']]), []), new SetLayoutTranslated('banner', 'tagline', 'da_DK', $source));

        self::assertSame(['tagline' => ['da_DK' => $source]], $tree->layout?->translated);

        $tree = $this->apply($tree, new SetLayoutTranslated('banner', 'tagline', 'da_DK', null));

        self::assertSame([], $tree->layout?->translated);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidLayoutChanges
     */
    public function it_refuses_a_layout_change_that_does_not_fit_the_entry(EntryInterface $entry, Change $change, string $message): void
    {
        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage($message);

        self::applier()->apply(new ChangeSet([$change]), $entry);
    }

    /**
     * @return iterable<string, array{EntryInterface, Change, string}>
     */
    public function provideInvalidLayoutChanges(): iterable
    {
        $banner = self::page(new StoredTree(new StoredLayout('banner', 3), []));
        $none = self::page(new StoredTree(null, []));
        $removed = self::page(new StoredTree(new StoredLayout('gone', 1), []));
        $recipe = new Recipe();

        yield 'switching the layout of a content type without layouts' => [$recipe, new SetLayout(new StoredLayout('standard', 1)), 'The content type "recipe" has no layouts'];
        yield 'switching to a layout with a field it does not have' => [$banner, new SetLayout(new StoredLayout('banner', 3, ['subtitle' => 'Hi'])), 'The layout "banner" has no field "subtitle"'];
        yield 'setting a value in a tree without a layout' => [$none, new SetLayoutValue('banner', 'tagline', 'en_US', 'Hi'), 'The tree has no layout "banner", so it can\'t take the change to its field "tagline"'];
        yield 'setting a value in a layout the tree is not in any more' => [$banner, new SetLayoutValue('standard', 'showTitle', null, false), 'The tree has no layout "standard", so it can\'t take the change to its field "showTitle"'];
        yield 'setting a value in a layout the code no longer has' => [$removed, new SetLayoutValue('gone', 'tagline', 'en_US', 'Hi'), 'The content type "page" has no layout with the code "gone"'];
        yield 'setting a field the layout does not have' => [$banner, new SetLayoutValue('banner', 'subtitle', 'en_US', 'Hi'), 'The layout "banner" has no field "subtitle"'];
        yield 'setting a translatable field without a locale' => [$banner, new SetLayoutValue('banner', 'tagline', null, 'Hi'), 'The field "tagline" is translatable, so a value needs a locale'];
        yield 'setting a shared field for one locale' => [$banner, new SetLayoutValue('banner', 'dark', 'en_US', true), 'The field "dark" is shared by all translations, so a value takes no locale'];
        yield 'setting the translation state in a layout the tree is not in any more' => [$banner, new SetLayoutTranslated('standard', 'showTitle', 'en_US', null), 'The tree has no layout "standard"'];
        yield 'setting the translation state of a field the layout does not have' => [$banner, new SetLayoutTranslated('banner', 'subtitle', 'en_US', null), 'The layout "banner" has no field "subtitle"'];
        yield 'setting the translation state of a shared field' => [$banner, new SetLayoutTranslated('banner', 'dark', 'en_US', null), 'The field "dark" is shared by all translations, so it has no translation state'];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidEntryChanges
     */
    public function it_refuses_an_entry_change_that_does_not_fit_the_content_type(EntryInterface $entry, Change $change, string $message): void
    {
        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage($message);

        self::applier()->apply(new ChangeSet([$change]), $entry);
    }

    /**
     * @return iterable<string, array{EntryInterface, Change, string}>
     */
    public function provideInvalidEntryChanges(): iterable
    {
        $page = self::page(new StoredTree(null, []));
        $recipe = new Recipe();

        yield 'a field the entry does not have' => [$page, new SetEntryValue('subtitle', 'en_US', 'Hello'), 'The content type "page" has no entry field "subtitle"'];
        yield 'a slug of a content type without its own URL' => [$recipe, new SetEntryValue('slug', 'en_US', 'pancakes'), 'The content type "recipe" has no entry field "slug"'];
        yield 'a translatable field without a locale' => [$page, new SetEntryValue('title', null, 'About'), 'The entry field "title" is translatable, so a value needs a locale'];
        yield 'a title that is not a string' => [$page, new SetEntryValue('title', 'en_US', ['About']), 'The entry field "title" must be a string or null'];
        yield 'the translation state of a field the entry does not have' => [$page, new SetEntryTranslated('subtitle', 'en_US', null), 'The content type "page" has no entry field "subtitle"'];
        yield 'the translation state of a slug of a content type without its own URL' => [$recipe, new SetEntryTranslated('slug', 'en_US', null), 'The content type "recipe" has no entry field "slug"'];
        yield 'a meta title of a content type without its own URL' => [$recipe, new SetEntryValue('metaTitle', 'en_US', 'Pancakes'), 'The content type "recipe" has no entry field "metaTitle"'];
        yield 'a meta description of a content type without its own URL' => [$recipe, new SetEntryValue('metaDescription', 'en_US', 'Pancakes'), 'The content type "recipe" has no entry field "metaDescription"'];
        yield 'a meta description that is not a string' => [$page, new SetEntryValue('metaDescription', 'en_US', 7), 'The entry field "metaDescription" must be a string or null'];
        yield 'hide from search engines of a content type without its own URL' => [$recipe, new SetEntryValue('noindex', null, true), 'The content type "recipe" has no entry field "noindex"'];
        yield 'hide from search engines in a locale' => [$page, new SetEntryValue('noindex', 'en_US', true), 'The entry field "noindex" is shared by all translations, so a value takes no locale'];
        yield 'hide from search engines that is not true or false' => [$page, new SetEntryValue('noindex', null, 'yes'), 'The entry field "noindex" must be true, false or null'];
        yield 'the translation state of hide from search engines' => [$page, new SetEntryTranslated('noindex', 'en_US', null), 'The entry field "noindex" is shared by all translations, so it has no translation state'];
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

        yield 'adding a block with a key the tree has' => [new AddBlock(null, 0, new StoredBlock(self::A, 'heading', 1)), 'The tree already has a block with the key ' . self::A];
        yield 'adding a block whose child has a key the tree has' => [new AddBlock(null, 0, $child), 'The tree already has a block with the key ' . self::A];
        yield 'adding a block of an unknown type' => [new AddBlock(null, 0, new StoredBlock($other, 'nope', 1)), '"nope"'];
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
        yield 'setting the translation state of a block the tree does not have' => [new SetTranslated($other, 'text', 'en_US', null), 'The tree has no block with the key ' . $other];
        yield 'setting the translation state of a block of an unknown type' => [new SetTranslated(self::B, 'text', 'en_US', null), 'There is no block type with the code "removed"'];
        yield 'setting the translation state of a field the block type does not have' => [new SetTranslated(self::A, 'subtitle', 'en_US', null), 'The block type "heading" has no field "subtitle"'];
        yield 'setting the translation state of a shared field' => [new SetTranslated(self::A, 'level', 'en_US', null), 'The field "level" is shared by all translations, so it has no translation state'];
    }

    private static function fingerprint(string $value): string
    {
        return hash('sha256', json_encode($value, \JSON_THROW_ON_ERROR));
    }

    private function apply(StoredTree $tree, Change ...$changes): StoredTree
    {
        $page = self::page($tree);
        self::applier()->apply(new ChangeSet(array_values($changes)), $page);

        return $page->getBlockTree();
    }

    /**
     * A page in en_US with the tree, as a working copy. It holds the tree as it's given, rather than as JSON, so the
     * values the applier writes are compared as it writes them
     */
    private static function page(StoredTree $tree): Page
    {
        $page = new class() extends Page {
            private StoredTree $tree;

            public function getBlockTree(): StoredTree
            {
                return $this->tree;
            }

            public function setBlockTree(StoredTree $tree): void
            {
                $this->tree = $tree;
            }
        };
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');
        $page->setBlockTree($tree);

        return $page;
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
                DateTimeHandler::class => static fn (): DateTimeHandler => new DateTimeHandler(),
            ])),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
            Conditions::registry(),
            new LayoutRegistry(['page' => ['standard' => Standard::class, 'banner' => Banner::class]], new FieldReader()),
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

    /**
     * @test
     */
    public function it_changes_a_block_that_holds_a_block_of_a_type_the_code_no_longer_has(): void
    {
        $unknown = new StoredBlock(self::B, 'removed', 1, fields: ['content' => self::DIRTY]);
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1, children: [$unknown])]);

        $tree = $this->apply($tree, new SetValue(self::A, 'text', 'en_US', 'Hello'));

        self::assertSame(['text' => ['en_US' => 'Hello']], $tree->blocks[0]->fields);
        self::assertSame(['content' => self::DIRTY], $tree->blocks[0]->children[0]->fields);
    }

    /**
     * @test
     */
    public function it_keeps_a_block_of_a_type_the_code_no_longer_has_inside_an_added_block_as_it_is(): void
    {
        $tree = new StoredTree(null, [new StoredBlock(self::A, 'heading', 1)]);
        $copy = new StoredBlock(self::B, 'group', 1, children: [new StoredBlock(self::C, 'removed', 1, fields: ['content' => self::DIRTY])]);

        $tree = $this->apply($tree, new AddBlock(null, 1, $copy));

        self::assertSame([self::A, self::B, self::C], $tree->keys());
        self::assertSame(['content' => self::DIRTY], $tree->blocks[1]->children[0]->fields);
    }
}
