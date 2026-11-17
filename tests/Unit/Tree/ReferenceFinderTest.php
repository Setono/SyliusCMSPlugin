<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Hero;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ReferenceFinderTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const COVER = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const POSTER_EN = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

    private const POSTER_DA = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d03';

    private const IMAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d04';

    private const HERO = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d05';

    /**
     * @test
     */
    public function it_finds_the_references_of_every_field_of_every_block_with_where_they_are(): void
    {
        $child = new StoredBlock(self::B, 'gallery', 1, fields: ['images' => [self::IMAGE]]);
        $parent = new StoredBlock(self::A, 'gallery', 1, fields: [
            'heading' => ['en_US' => 'Hello'],
            'cover' => self::COVER,
            'poster' => ['en_US' => self::POSTER_EN, 'da_DK' => self::POSTER_DA],
        ], children: [$child]);

        self::assertEquals([
            new FoundReference(new Reference('media_item', self::COVER, true), self::A, 'cover', null),
            new FoundReference(new Reference('media_item', self::POSTER_EN, true), self::A, 'poster', 'en_US'),
            new FoundReference(new Reference('media_item', self::POSTER_DA, true), self::A, 'poster', 'da_DK'),
            new FoundReference(new Reference('media_item', self::IMAGE, true), self::B, 'images', null),
        ], [...$this->finder()->inBlocks([$parent])]);
    }

    /**
     * @test
     */
    public function it_leaves_out_blocks_of_a_type_the_code_no_longer_has_with_their_children(): void
    {
        $child = new StoredBlock(self::B, 'gallery', 1, fields: ['cover' => self::COVER]);
        $unknown = new StoredBlock(self::A, 'removed', 1, fields: ['cover' => self::IMAGE], children: [$child]);

        self::assertSame([], [...$this->finder()->inBlocks([$unknown])]);
    }

    /**
     * @test
     */
    public function it_reads_a_translatable_value_that_is_not_a_locale_map_as_holding_nothing(): void
    {
        $block = new StoredBlock(self::A, 'gallery', 1, fields: ['poster' => self::POSTER_EN]);

        self::assertSame([], [...$this->finder()->inBlocks([$block])]);
    }

    /**
     * @test
     */
    public function it_finds_the_references_of_the_layout_and_the_blocks_of_a_tree(): void
    {
        $tree = new StoredTree(new StoredLayout('hero', 1, ['image' => self::HERO]), [new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::COVER])]);

        self::assertEquals([
            new FoundReference(new Reference('media_item', self::HERO, true), null, 'image', null),
            new FoundReference(new Reference('media_item', self::COVER, true), self::A, 'cover', null),
        ], [...$this->finder()->inTree($tree, 'page')]);
    }

    /**
     * @test
     */
    public function it_leaves_out_the_layout_of_a_tree_when_the_code_no_longer_has_it(): void
    {
        $tree = new StoredTree(new StoredLayout('removed', 1, ['image' => self::HERO]), []);

        self::assertSame([], [...$this->finder()->inTree($tree, 'page')]);
    }

    /**
     * @test
     */
    public function it_gives_only_the_references(): void
    {
        $block = new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::COVER]);

        self::assertEquals([new Reference('media_item', self::COVER, true)], [...ReferenceFinder::references($this->finder()->inBlocks([$block]))]);
    }

    private function finder(): ReferenceFinder
    {
        return new ReferenceFinder(
            new TreeValues(
                new BlockTypeRegistry(['gallery' => Gallery::class], new FieldReader()),
                new LayoutRegistry(['page' => ['hero' => Hero::class]], new FieldReader()),
            ),
            new FieldHandlerLocator(new ServiceLocator([
                TextHandler::class => static fn (): TextHandler => new TextHandler(),
                MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
                MediaListHandler::class => static fn (): MediaListHandler => new MediaListHandler(),
            ])),
        );
    }
}
