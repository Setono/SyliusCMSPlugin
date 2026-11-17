<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\Taxons;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithCatalog;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * The Products and Taxons field types: hand-picked lists of codes, hydrated from the references loaded before rendering,
 * which hold only what the shopper may see. See "List fields" in docs/spec/sources.md
 */
final class ListFieldHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_products_and_taxons_field_types_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithCatalog::class);

        self::assertSame('products', $fields['products']->type->code);
        self::assertFalse($fields['products']->translatable);
        self::assertSame(ListFieldHandler::class, $fields['products']->handler());
        self::assertInstanceOf(Products::class, $fields['products']->field);
        self::assertSame(12, $fields['products']->field->max);
        self::assertSame(['bestsellers'], $fields['products']->field->sources);
        self::assertSame(4, $fields['products']->field->limit);

        self::assertSame('taxons', $fields['taxons']->type->code);
        self::assertFalse($fields['taxons']->translatable);
        self::assertSame(ListFieldHandler::class, $fields['taxons']->handler());
        self::assertInstanceOf(Taxons::class, $fields['taxons']->field);
        self::assertFalse($fields['taxons']->field->sources);
    }

    /**
     * @test
     */
    public function it_offers_every_source_shows_eight_items_and_has_no_most_unless_it_says_otherwise(): void
    {
        $field = new Products(label: 'Products');

        self::assertNull($field->max);
        self::assertTrue($field->sources);
        self::assertSame(8, $field->limit);
    }

    /**
     * @test
     */
    public function it_lists_products_or_taxons_by_their_reference_type(): void
    {
        self::assertSame('product', (new Products(label: 'Products'))->itemType());
        self::assertSame(ProductInterface::class, (new Products(label: 'Products'))->itemClass());
        self::assertSame('taxon', (new Taxons(label: 'Taxons'))->itemType());
        self::assertSame(TaxonInterface::class, (new Taxons(label: 'Taxons'))->itemClass());
    }

    /**
     * @test
     */
    public function it_refuses_a_most_or_a_limit_below_one(): void
    {
        foreach ([static fn (): Products => new Products(label: 'Products', max: 0), static fn (): Taxons => new Taxons(label: 'Taxons', limit: 0)] as $make) {
            try {
                $make();
                self::fail('A most or a limit below one is refused');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @test
     */
    public function it_describes_a_list_of_codes_with_its_most_sources_and_limit_in_its_schema(): void
    {
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'minLength' => 1],
                'maxItems' => 12,
                'x-cms-options' => ['max' => 12, 'sources' => ['bestsellers'], 'limit' => 4],
            ],
            (new ListFieldHandler())->schema(new Products(label: 'Products', max: 12, sources: ['bestsellers'], limit: 4)),
        );
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'minLength' => 1],
                'x-cms-options' => ['max' => null, 'sources' => true, 'limit' => 8],
            ],
            (new ListFieldHandler())->schema(new Taxons(label: 'Taxons')),
        );
    }

    /**
     * @test
     */
    public function it_keeps_the_editor_s_order_and_leaves_out_the_products_shoppers_cannot_see(): void
    {
        $mug = self::product('MUG');
        $cap = self::product('CAP');

        // The references loaded before rendering hold only the products the shopper may see: T_SHIRT is disabled
        $list = (new ListFieldHandler())->hydrate(
            new Products(label: 'Products'),
            ['MUG', 'T_SHIRT', 'CAP'],
            $this->hydrationContext(['product' => ['CAP' => $cap, 'MUG' => $mug]]),
        );

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$mug, $cap], [...$list]);
        self::assertCount(2, $list);
    }

    /**
     * @test
     */
    public function it_shows_every_hand_picked_item_whatever_its_limit(): void
    {
        $products = ['A' => self::product('A'), 'B' => self::product('B'), 'C' => self::product('C')];

        $list = (new ListFieldHandler())->hydrate(new Products(label: 'Products', limit: 2), ['C', 'A', 'B'], $this->hydrationContext(['product' => $products]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$products['C'], $products['A'], $products['B']], $list->toArray());
    }

    /**
     * @test
     */
    public function it_hydrates_taxons_from_the_taxon_references(): void
    {
        $shirts = self::taxon('T_SHIRTS');

        $list = (new ListFieldHandler())->hydrate(
            new Taxons(label: 'Taxons'),
            ['MUG', 'T_SHIRTS'],
            $this->hydrationContext(['product' => ['MUG' => self::product('MUG')], 'taxon' => ['T_SHIRTS' => $shirts]]),
        );

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$shirts], [...$list]);
    }

    /**
     * @test
     */
    public function it_hydrates_a_list_whose_items_shoppers_cannot_see_as_an_empty_list(): void
    {
        $list = (new ListFieldHandler())->hydrate(new Products(label: 'Products'), ['T_SHIRT'], $this->hydrationContext([]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertTrue($list->isEmpty());
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = new ListFieldHandler();
        $field = new Products(label: 'Products');
        $context = $this->hydrationContext(['product' => ['MUG' => self::product('MUG')]]);

        self::assertNull($handler->hydrate($field, 'MUG', $context));
        self::assertNull($handler->hydrate($field, ['MUG', 7], $context));
        self::assertNull($handler->hydrate($field, ['MUG', ''], $context));
        self::assertNull($handler->hydrate($field, ['a' => 'MUG'], $context));
    }

    /**
     * @test
     */
    public function it_fits_a_list_of_codes_even_one_longer_than_its_most(): void
    {
        $handler = new ListFieldHandler();
        $field = new Products(label: 'Products', max: 1);

        self::assertTrue($handler->fits($field, ['MUG', 'CAP']));
        self::assertTrue($handler->fits($field, []));
        self::assertFalse($handler->fits($field, 'MUG'));
        self::assertFalse($handler->fits($field, ['MUG', 7]));
        self::assertFalse($handler->fits($field, ['a' => 'MUG']));
    }

    /**
     * @test
     */
    public function it_refers_to_each_item_once_as_a_link_that_an_export_leaves_as_a_code(): void
    {
        self::assertEquals(
            [new Reference('product', 'MUG', false), new Reference('product', 'CAP', false)],
            [...(new ListFieldHandler())->references(new Products(label: 'Products'), ['MUG', 'CAP', 'MUG', 7, ''])],
        );
        self::assertEquals(
            [new Reference('taxon', 'T_SHIRTS', false)],
            [...(new ListFieldHandler())->references(new Taxons(label: 'Taxons'), ['T_SHIRTS'])],
        );
    }

    /**
     * @test
     */
    public function it_refers_to_nothing_when_the_value_is_not_a_list(): void
    {
        self::assertSame([], [...(new ListFieldHandler())->references(new Products(label: 'Products'), 'MUG')]);
        self::assertSame([], [...(new ListFieldHandler())->references(new Products(label: 'Products'), ['a' => 'MUG'])]);
    }

    /**
     * @test
     */
    public function it_accepts_a_list_of_codes_up_to_its_most_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new ListFieldHandler();
        $field = new Products(label: 'Products', max: 2);
        $handler->validate($field, ['MUG', 'CAP'], $context->reveal());
        $handler->validate($field, [], $context->reveal());
        $handler->validate($field, null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_list_of_codes(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_list')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new ListFieldHandler())->validate(new Taxons(label: 'Taxons'), ['T_SHIRTS', 7], $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_more_items_than_its_most(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setParameter('{{ limit }}', '1')->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->setPlural(1)->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.too_many_items')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new ListFieldHandler())->validate(new Products(label: 'Products', max: 1), ['MUG', 'CAP'], $context->reveal());
    }

    private static function product(string $code): ProductInterface
    {
        $product = new Product();
        $product->setCode($code);

        return $product;
    }

    private static function taxon(string $code): TaxonInterface
    {
        $taxon = new Taxon();
        $taxon->setCode($code);

        return $taxon;
    }

    /**
     * @param array<string, array<string, object>> $records by reference type, then by code
     */
    private function hydrationContext(array $records): HydrationContext
    {
        return new HydrationContext(
            new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable()),
            new ReferenceMap($records),
        );
    }
}
