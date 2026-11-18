<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Context\BlockDependencies;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preset\PresetFitCheck;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\NestingRules;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ProductBadge;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Where a preset is offered: where every one of its top-level blocks is allowed, inserting it keeps the spot within its
 * max, and the context its blocks, sources and bindings need is available. See "Where a preset is offered" in
 * docs/spec/layouts-and-presets.md
 */
final class PresetFitCheckTest extends TestCase
{
    private const COLUMNS = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_offers_a_preset_whose_top_level_blocks_the_spot_allows(): void
    {
        $fit = self::check()->at([self::block('heading'), self::block('columns')], self::page([]), null);

        self::assertTrue($fit->fits());
        self::assertSame([], $fit->reasons);
    }

    /**
     * @test
     */
    public function it_names_each_top_level_block_type_the_spot_does_not_allow(): void
    {
        $fit = self::check()->at([self::block('column'), self::block('column'), self::block('heading')], self::page([]), null);

        self::assertFalse($fit->fits());
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.fit.not_allowed', ['%block%' => new TranslatableMessage('setono_sylius_cms.block_type.column')])], $fit->reasons);
    }

    /**
     * @test
     */
    public function it_offers_a_preset_inside_a_block_with_room_for_its_blocks_and_not_where_it_would_go_over_the_max(): void
    {
        $pair = [self::block('column'), self::block('column')];
        $check = self::check();

        self::assertTrue($check->at($pair, self::page([self::columns(2)]), self::COLUMNS)->fits());

        $fit = $check->at($pair, self::page([self::columns(3)]), self::COLUMNS);
        self::assertFalse($fit->fits());
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.fit.too_many', ['%count%' => 4, '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.column')])], $fit->reasons);
    }

    /**
     * @test
     */
    public function it_does_not_offer_a_preset_inside_a_block_the_tree_does_not_have(): void
    {
        self::assertFalse(self::check()->at([self::block('heading')], self::page([]), self::COLUMNS)->fits());
    }

    /**
     * @test
     */
    public function it_does_not_offer_a_preset_whose_blocks_need_context_the_entry_does_not_provide_naming_it(): void
    {
        $badge = self::block('heading', [self::block('product_badge')]);
        $check = self::check();

        $fit = $check->at([self::block('columns', [self::block('column', [self::block('product_badge')])])], self::page([]), null);
        self::assertFalse($fit->fits());
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.fit.needs_context', ['%context%' => new TranslatableMessage('setono_sylius_cms.context_key.product')])], $fit->reasons);

        $snippet = new Snippet();
        $snippet->setContext(['product']);
        self::assertTrue($check->at([$badge], $snippet, null)->fits());
    }

    /**
     * @test
     */
    public function it_does_not_offer_a_preset_with_nothing_to_insert(): void
    {
        $fit = self::check()->at([], self::page([]), null);

        self::assertFalse($fit->fits());
        self::assertEquals([new TranslatableMessage('setono_sylius_cms.preset.fit.nothing_to_insert')], $fit->reasons);
    }

    private static function check(): PresetFitCheck
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'columns' => Columns::class, 'column' => Column::class, 'product_badge' => ProductBadge::class], new FieldReader());
        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
        ]);

        return new PresetFitCheck(
            new NestingRules($blockTypes, $contentTypes),
            $blockTypes,
            $contentTypes,
            [new BlockDependencies($blockTypes)],
            new ContextKeyRegistry(['product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product']]),
        );
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function page(array $blocks): EntryInterface
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, $blocks));

        return $page;
    }

    private static function columns(int $columns): StoredBlock
    {
        return new StoredBlock(self::COLUMNS, 'columns', 1, children: array_map(static fn (): StoredBlock => self::block('column'), range(1, $columns)));
    }

    /**
     * @param list<StoredBlock> $children
     */
    private static function block(string $type, array $children = []): StoredBlock
    {
        return new StoredBlock(sprintf('0193a5be-0000-7000-8000-%012d', random_int(2, 999999)), $type, 1, children: $children);
    }
}
