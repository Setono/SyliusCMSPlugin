<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Products;
use Setono\SyliusCMSPlugin\Block\Taxons;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownSource;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture\Kiosk;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture\Showcase;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\Clearance;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A list filled by a source the code no longer has yields nothing, and the admin flags it. See "The contract" in
 * docs/spec/sources.md and "Problems" in docs/spec/admin.md
 */
final class UnknownSourceTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    /**
     * @test
     */
    public function it_finds_a_list_filled_by_a_source_the_code_no_longer_has_as_a_warning_fixed_in_the_builder_at_the_block(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'products', 1, fields: ['products' => self::stored('bestsellers')]),
            new StoredBlock(self::B, 'group', 1, children: [new StoredBlock(self::C, 'taxons', 1, fields: ['taxons' => self::stored('removed')])]),
        );

        self::assertEquals([
            self::problem($page, self::A, 'setono_sylius_cms.block_type.products', 'products', 'setono_sylius_cms.field.products.products', 'bestsellers'),
            self::problem($page, self::C, 'setono_sylius_cms.block_type.taxons', 'taxons', 'setono_sylius_cms.field.taxons.taxons', 'removed'),
        ], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_a_list_filled_by_a_source_the_code_has_even_a_disabled_one_or_a_hand_picked_list(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'products', 1, fields: ['products' => self::stored('on_sale')]),
            new StoredBlock(self::B, 'products', 1, fields: ['products' => self::stored('clearance')]),
            new StoredBlock(self::C, 'products', 1, fields: ['products' => ['MUG', 'CAP']]),
            // Of a type the code no longer has, which another problem flags
            new StoredBlock(self::D, 'promo', 1, fields: ['products' => self::stored('removed')]),
        );

        self::assertSame([], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_list_of_the_layout_filled_by_a_source_the_code_no_longer_has(): void
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(new StoredLayout('showcase', 1, ['products' => self::stored('removed')]), []));

        self::assertEquals([new Problem(
            'unknown_source',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_layout_source', ['%field%' => new TranslatableMessage('setono_sylius_cms.field.products.products'), '%source%' => 'removed']),
            Fix::inBuilder($page),
            null,
            'products',
        )], [...self::kind()->findIn($page)]);
    }

    /**
     * A content type's own list fields are fields like any other, fixed in the builder's settings
     *
     * @test
     */
    public function it_finds_a_list_of_one_of_the_content_types_own_fields_filled_by_a_source_the_code_no_longer_has(): void
    {
        $kiosk = new Kiosk();
        $kiosk->picks = self::stored('removed');

        self::assertEquals([new Problem(
            'unknown_source',
            Severity::Warning,
            $kiosk,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_entry_source', ['%field%' => new TranslatableMessage('app.cms.field.picks'), '%source%' => 'removed']),
            Fix::inBuilder($kiosk),
            null,
            'picks',
        )], [...self::kind()->findIn($kiosk)]);
    }

    private static function problem(Page $page, string $block, string $blockLabel, string $field, string $fieldLabel, string $source): Problem
    {
        return new Problem(
            'unknown_source',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_source', [
                '%field%' => new TranslatableMessage($fieldLabel),
                '%block%' => new TranslatableMessage($blockLabel),
                '%source%' => $source,
            ]),
            Fix::inBuilder($page, $block),
            $block,
            $field,
        );
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

    private static function kind(): UnknownSource
    {
        return new UnknownSource(
            new TreeValues(new BlockTypeRegistry(['products' => Products::class, 'taxons' => Taxons::class, 'group' => Group::class], new FieldReader()), new LayoutRegistry(['page' => ['showcase' => Showcase::class]], new FieldReader())),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'kiosk' => ['model' => Kiosk::class, 'translation' => EntryTranslation::class, 'resource' => 'app.kiosk'],
            ]),
            Sources::registry(['on_sale' => new OnSale(), 'clearance' => new Clearance()], ['clearance']),
        );
    }
}
