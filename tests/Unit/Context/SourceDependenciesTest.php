<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Entries;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Products;
use Setono\SyliusCMSPlugin\Block\Taxons;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Context\SourceDependencies;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\CategoryPosts;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\LocalizedPicks;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\Lookbook;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\Neighbours;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture\Showcase;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\ProductTaxons;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A list filled by a source that needs context keys depends on the entry providing them, wherever it sits in the tree
 * or in the layout. See "The contract" in docs/spec/sources.md
 */
final class SourceDependenciesTest extends TestCase
{
    private const OUTER = '0193a5be-0000-7000-8000-000000000001';

    private const NESTED = '0193a5be-0000-7000-8000-000000000002';

    private const LOCALIZED = '0193a5be-0000-7000-8000-000000000003';

    private const PRODUCTS = '0193a5be-0000-7000-8000-000000000009';

    /**
     * @test
     */
    public function it_finds_every_list_filled_by_a_source_that_needs_context_keys_with_the_key_of_its_block(): void
    {
        $page = self::page(
            new StoredBlock(self::OUTER, 'taxons', 1, fields: ['taxons' => self::stored('product_taxons')]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'group', 1, children: [
                new StoredBlock(self::NESTED, 'taxons', 1, fields: ['taxons' => self::stored('product_taxons')]),
                new StoredBlock(self::PRODUCTS, 'products', 1, fields: ['products' => self::stored('neighbours')]),
                // A source that needs nothing, one the code no longer has, one of another kind, which yields nothing, and a
                // hand-picked list need nothing
                new StoredBlock('0193a5be-0000-7000-8000-000000000008', 'products', 1, fields: ['products' => self::stored('product_taxons')]),
                new StoredBlock('0193a5be-0000-7000-8000-000000000005', 'products', 1, fields: ['products' => self::stored('on_sale')]),
                new StoredBlock('0193a5be-0000-7000-8000-000000000006', 'products', 1, fields: ['products' => self::stored('removed')]),
                new StoredBlock('0193a5be-0000-7000-8000-000000000007', 'taxons', 1, fields: ['taxons' => ['T_SHIRTS']]),
            ]),
        );

        self::assertEquals([
            new ContextDependency(self::inBlock('app.source.product_taxons', 'setono_sylius_cms.block_type.taxons'), ['product'], block: self::OUTER),
            new ContextDependency(self::inBlock('app.source.product_taxons', 'setono_sylius_cms.block_type.taxons'), ['product'], block: self::NESTED),
            new ContextDependency(self::inBlock('app.source.neighbours', 'setono_sylius_cms.block_type.products'), ['product', 'taxon'], block: self::PRODUCTS),
        ], [...self::finder()->findIn($page)]);
    }

    /**
     * A disabled source keeps resolving, so it still needs its context
     *
     * @test
     */
    public function it_finds_a_list_filled_by_a_disabled_source(): void
    {
        $page = self::page(new StoredBlock(self::OUTER, 'taxons', 1, fields: ['taxons' => self::stored('product_taxons')]));

        self::assertEquals([
            new ContextDependency(self::inBlock('app.source.product_taxons', 'setono_sylius_cms.block_type.taxons'), ['product'], block: self::OUTER),
        ], [...self::finder(['product_taxons'])->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_list_filled_per_locale_once(): void
    {
        $page = self::page(new StoredBlock(self::LOCALIZED, 'localized_picks', 1, fields: ['taxons' => [
            'en_US' => self::stored('product_taxons'),
            'da_DK' => self::stored('product_taxons'),
        ]]));

        self::assertEquals([
            new ContextDependency(self::inBlock('app.source.product_taxons', 'app.block_type.localized_picks'), ['product'], block: self::LOCALIZED),
        ], [...self::finder()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_list_of_the_layout_filled_by_a_source_that_needs_context_keys(): void
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(new StoredLayout('showcase', 1, ['products' => self::stored('neighbours')]), []));

        self::assertEquals([
            new ContextDependency(self::inLayout('app.source.neighbours', 'setono_sylius_cms.field.products.products'), ['product', 'taxon']),
        ], [...self::finder()->findIn($page)]);
    }

    /**
     * A content type's own list field is a field like any other (docs/spec/extending.md#content-types)
     *
     * @test
     */
    public function it_finds_a_list_of_the_content_types_own_fields_filled_by_a_source_that_needs_context_keys(): void
    {
        $lookbook = new Lookbook();
        $lookbook->picks = self::stored('neighbours');

        self::assertEquals([
            new ContextDependency(self::inEntry('app.source.neighbours', 'app.cms.field.picks'), ['product', 'taxon']),
        ], [...self::finder()->findIn($lookbook)]);
    }

    /**
     * A source that fits only some content types as the entry, such as In this category, depends on the entry being of
     * one of them (docs/spec/extending.md#sources)
     *
     * @test
     */
    public function it_finds_the_content_types_whose_entries_a_source_that_needs_the_entry_fits(): void
    {
        $page = self::page(new StoredBlock(self::OUTER, 'entries', 1, fields: ['entries' => self::stored('category_posts')]));

        self::assertEquals([
            new ContextDependency(self::inBlock('app.source.category_posts', 'setono_sylius_cms.block_type.entries'), ['entry'], block: self::OUTER, entryContentTypes: ['blog_category']),
        ], [...self::finder()->findIn($page)]);
    }

    private static function inEntry(string $source, string $field): TranslatableMessage
    {
        return new TranslatableMessage('setono_sylius_cms.context.entry_source', ['%source%' => new TranslatableMessage($source), '%field%' => new TranslatableMessage($field)], 'validators');
    }

    private static function inLayout(string $source, string $field): TranslatableMessage
    {
        return new TranslatableMessage('setono_sylius_cms.context.layout_source', ['%source%' => new TranslatableMessage($source), '%field%' => new TranslatableMessage($field)], 'validators');
    }

    private static function inBlock(string $source, string $block): TranslatableMessage
    {
        return new TranslatableMessage('setono_sylius_cms.context.source', ['%source%' => new TranslatableMessage($source), '%block%' => new TranslatableMessage($block)], 'validators');
    }

    /**
     * @return array{'$source': string, '$params': array<never>, '$limit': int}
     */
    private static function stored(string $code): array
    {
        return ['$source' => $code, '$params' => [], '$limit' => 8];
    }

    private static function page(StoredBlock ...$blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $page;
    }

    /**
     * @param list<string> $disabled
     */
    private static function finder(array $disabled = []): SourceDependencies
    {
        return new SourceDependencies(
            new TreeValues(
                new BlockTypeRegistry(['products' => Products::class, 'taxons' => Taxons::class, 'group' => Group::class, 'localized_picks' => LocalizedPicks::class, 'entries' => Entries::class], new FieldReader()),
                new LayoutRegistry(['page' => ['showcase' => Showcase::class]], new FieldReader()),
            ),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
                'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
                'lookbook' => ['model' => Lookbook::class, 'translation' => EntryTranslation::class, 'resource' => 'app.lookbook'],
            ]),
            Sources::registry(['on_sale' => new OnSale(), 'product_taxons' => new ProductTaxons(), 'neighbours' => new Neighbours(), 'category_posts' => new CategoryPosts()], $disabled),
        );
    }
}
