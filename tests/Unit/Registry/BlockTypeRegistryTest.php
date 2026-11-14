<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\UnknownBlockTypeException;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\NotABlockType;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;

final class BlockTypeRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_block_type_from_its_attribute_and_its_fields(): void
    {
        $registry = new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader());

        $blockType = $registry->get('teaser');

        self::assertSame('teaser', $blockType->code);
        self::assertSame('app.teaser', $blockType->label);
        self::assertSame('teaser.html.twig', $blockType->template);
        self::assertSame(3, $blockType->version);
        self::assertSame(Teaser::class, $blockType->class);
        self::assertSame(['heading', 'code', 'size'], array_keys($blockType->fields));
    }

    /**
     * @test
     */
    public function a_block_type_is_at_version_1_unless_it_says_otherwise(): void
    {
        self::assertSame(1, (new BlockTypeRegistry(['plain' => Plain::class], new FieldReader()))->get('plain')->version);
    }

    /**
     * @test
     */
    public function it_reads_each_block_type_once(): void
    {
        $registry = new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader());

        self::assertSame($registry->get('teaser'), $registry->get('teaser'));
    }

    /**
     * @test
     */
    public function it_knows_which_block_types_it_has(): void
    {
        $registry = new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader());

        self::assertTrue($registry->has('teaser'));
        self::assertFalse($registry->has('heading'));
    }

    /**
     * @test
     */
    public function it_lists_every_block_type_in_order(): void
    {
        $registry = new BlockTypeRegistry(['teaser' => Teaser::class, 'heading' => Heading::class], new FieldReader());

        self::assertSame(['teaser', 'heading'], array_map(static fn ($blockType) => $blockType->code, $registry->all()));
    }

    /**
     * @test
     */
    public function it_throws_on_an_unknown_block_type(): void
    {
        $this->expectException(UnknownBlockTypeException::class);
        $this->expectExceptionMessage('"heading"');

        (new BlockTypeRegistry([], new FieldReader()))->get('heading');
    }

    /**
     * @test
     */
    public function it_throws_when_a_class_has_no_block_type_attribute(): void
    {
        $this->expectException(\LogicException::class);

        (new BlockTypeRegistry(['teaser' => NotABlockType::class], new FieldReader()))->get('teaser');
    }

    /**
     * @test
     */
    public function it_ships_the_heading_block_type(): void
    {
        $heading = (new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()))->get('heading');

        self::assertSame('setono_sylius_cms.block_type.heading', $heading->label);
        self::assertSame('@SetonoSyliusCMSPlugin/block/heading.html.twig', $heading->template);
        self::assertSame(1, $heading->version);
        self::assertSame(['text', 'level'], array_keys($heading->fields));
        self::assertTrue($heading->fields['text']->field->required);
        self::assertTrue($heading->fields['text']->translatable);
        self::assertFalse($heading->fields['level']->translatable);
        self::assertSame('h2', $heading->fields['level']->default);
    }

    /**
     * @test
     */
    public function it_reads_what_a_block_type_may_hold_where_it_may_sit_and_its_thumbnail(): void
    {
        $registry = new BlockTypeRegistry(['columns' => Columns::class, 'column' => Column::class], new FieldReader());

        $columns = $registry->get('columns');
        self::assertEquals([new Allow(Column::class, 2, 4)], $columns->children);
        self::assertNull($columns->parents);
        self::assertSame('/bundles/setonosyliuscmsplugin/thumbnail/block/columns.svg', $columns->thumbnail);

        $column = $registry->get('column');
        self::assertEquals([new Allow('*')], $column->children);
        self::assertSame([Columns::class], $column->parents);
    }

    /**
     * @test
     */
    public function it_finds_a_block_type_by_its_class(): void
    {
        $registry = new BlockTypeRegistry(['teaser' => Teaser::class, 'plain' => Plain::class], new FieldReader());

        self::assertSame('plain', $registry->getByClass(Plain::class)->code);
        self::assertSame($registry->get('teaser'), $registry->getByClass(Teaser::class));
    }

    /**
     * @test
     */
    public function it_throws_on_a_class_that_is_no_registered_block_type(): void
    {
        $this->expectException(UnknownBlockTypeException::class);
        $this->expectExceptionMessage(sprintf('The class %s is no registered block type', Heading::class));

        (new BlockTypeRegistry(['plain' => Plain::class], new FieldReader()))->getByClass(Heading::class);
    }

    /**
     * @test
     */
    public function it_keeps_a_disabled_block_type_so_blocks_of_it_keep_rendering_and_marks_it_disabled(): void
    {
        $registry = new BlockTypeRegistry(['teaser' => Teaser::class, 'plain' => Plain::class], new FieldReader(), ['teaser']);

        self::assertTrue($registry->has('teaser'));
        self::assertTrue($registry->get('teaser')->disabled);
        self::assertFalse($registry->get('plain')->disabled);
        self::assertCount(2, $registry->all());
    }

    /**
     * @test
     */
    public function a_block_type_holds_nothing_sits_anywhere_and_has_no_thumbnail_unless_it_says_otherwise(): void
    {
        $plain = (new BlockTypeRegistry(['plain' => Plain::class], new FieldReader()))->get('plain');

        self::assertSame([], $plain->children);
        self::assertNull($plain->parents);
        self::assertNull($plain->thumbnail);
    }

    /**
     * @test
     */
    public function it_merges_the_styles_a_block_type_declares_with_those_a_project_adds_in_config(): void
    {
        $registry = new BlockTypeRegistry(['group' => Group::class, 'plain' => Plain::class], new FieldReader(), [], [
            'group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']],
            'plain' => ['boxed' => ['label' => 'app.cms.block_style.boxed', 'class' => 'app-plain--boxed']],
        ]);

        self::assertEquals([
            'muted' => new Style('muted', 'setono_sylius_cms.block_style.muted', 'cms-group--muted'),
            'dark' => new Style('dark', 'setono_sylius_cms.block_style.dark', 'cms-group--dark'),
            'brand' => new Style('brand', 'app.cms.block_style.brand', 'app-group--brand'),
        ], $registry->get('group')->styles);
        self::assertEquals(['boxed' => new Style('boxed', 'app.cms.block_style.boxed', 'app-plain--boxed')], $registry->get('plain')->styles);
    }

    /**
     * @test
     */
    public function a_style_added_in_config_with_the_code_of_a_declared_one_replaces_it_in_its_place(): void
    {
        $registry = new BlockTypeRegistry(['group' => Group::class], new FieldReader(), [], [
            'group' => ['muted' => ['label' => 'app.cms.block_style.soft', 'class' => 'app-group--soft']],
        ]);

        self::assertEquals([
            'muted' => new Style('muted', 'app.cms.block_style.soft', 'app-group--soft'),
            'dark' => new Style('dark', 'setono_sylius_cms.block_style.dark', 'cms-group--dark'),
        ], $registry->get('group')->styles);
    }

    /**
     * @test
     */
    public function a_block_type_offers_no_styles_unless_it_or_the_config_adds_some(): void
    {
        self::assertSame([], (new BlockTypeRegistry(['plain' => Plain::class], new FieldReader()))->get('plain')->styles);
    }
}
