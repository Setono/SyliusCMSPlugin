<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
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
}
