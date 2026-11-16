<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Spacer;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Card;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Panel;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Slide;

final class NestingRulesTest extends TestCase
{
    /**
     * @test
     */
    public function it_allows_the_block_types_a_block_type_names_with_their_min_and_max(): void
    {
        self::assertEquals([new AllowedBlockType('column', 2, 4)], self::rules()->children('columns'));
    }

    /**
     * @test
     */
    public function it_allows_nothing_inside_a_block_type_that_allows_no_children(): void
    {
        self::assertSame([], self::rules()->children('heading'));
        self::assertSame([], self::rules()->children('spacer'));
    }

    /**
     * @test
     */
    public function it_lets_star_allow_every_block_type_except_those_that_limit_their_parents_to_others(): void
    {
        self::assertEquals([
            new AllowedBlockType('group', null, null),
            new AllowedBlockType('columns', null, null),
            new AllowedBlockType('spacer', null, null),
            new AllowedBlockType('heading', null, null),
            new AllowedBlockType('gallery', null, null),
            new AllowedBlockType('card', null, null),
        ], self::rules()->children('group'));
    }

    /**
     * @test
     */
    public function it_lets_star_allow_a_block_type_inside_the_parents_it_limits_itself_to(): void
    {
        $codes = self::codes(self::rules()->children('gallery'));

        self::assertContains('slide', $codes);
        self::assertNotContains('column', $codes);
    }

    /**
     * @test
     */
    public function it_lets_a_block_type_named_by_its_class_win_over_star_and_leaves_it_out_where_its_parents_do_not_allow_it(): void
    {
        $children = self::rules()->children('card');

        self::assertEquals(new AllowedBlockType('heading', 1, 1), $children[3]);
        self::assertSame(['group', 'columns', 'spacer', 'heading', 'gallery', 'card'], self::codes($children));
    }

    /**
     * @test
     */
    public function it_keeps_the_min_and_max_of_a_block_type_named_by_its_class_before_star(): void
    {
        $children = self::rules(extra: ['panel' => Panel::class])->children('panel');

        self::assertEquals(new AllowedBlockType('heading', null, 2), $children[0]);
        self::assertContains('group', self::codes($children));
    }

    /**
     * @test
     */
    public function it_applies_the_same_rules_to_the_top_level_as_to_children_with_no_parent(): void
    {
        self::assertSame(['group', 'columns', 'spacer', 'heading', 'gallery', 'card'], self::codes(self::rules()->topLevel('page')));
    }

    /**
     * @test
     */
    public function it_allows_nothing_at_the_top_level_of_a_content_type_without_a_block_tree(): void
    {
        self::assertSame([], self::rules()->topLevel('recipe'));
    }

    /**
     * @test
     */
    public function it_still_allows_a_disabled_block_type_where_it_was_so_blocks_of_it_stay_and_keep_rendering(): void
    {
        $rules = self::rules(disabled: ['spacer']);

        self::assertContains('spacer', self::codes($rules->children('group')));
        self::assertContains('spacer', self::codes($rules->topLevel('page')));
    }

    /**
     * @param list<string> $disabled
     * @param array<string, class-string> $extra more block types by code
     */
    private static function rules(array $disabled = [], array $extra = []): NestingRules
    {
        return new NestingRules(
            new BlockTypeRegistry([
                'group' => Group::class,
                'columns' => Columns::class,
                'column' => Column::class,
                'spacer' => Spacer::class,
                'heading' => Heading::class,
                'gallery' => Gallery::class,
                'slide' => Slide::class,
                'card' => Card::class,
            ] + $extra, new FieldReader(), $disabled),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
        );
    }

    /**
     * @param list<AllowedBlockType> $allowed
     *
     * @return list<string>
     */
    private static function codes(array $allowed): array
    {
        return array_map(static fn (AllowedBlockType $type): string => $type->code, $allowed);
    }
}
