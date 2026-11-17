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
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Support\Violations;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithCatalog;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\Clearance;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\ProductTaxons;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * The Products and Taxons field types: hand-picked lists of codes, hydrated from the references loaded before rendering,
 * which hold only what the shopper may see, or filled by a source, stored as {"$source", "$params", "$limit"},
 * resolved when a template first reads it, showing the source's first $limit items. See "List fields" and "The
 * contract" in docs/spec/sources.md
 */
final class ListFieldHandlerTest extends TestCase
{
    use ProphecyTrait;
    use Violations;

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
    public function it_describes_a_list_of_codes_with_its_most_and_limit_in_its_schema_when_no_source_fits(): void
    {
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'minLength' => 1],
                'maxItems' => 12,
                'x-cms-options' => ['max' => 12, 'sources' => [], 'limit' => 4],
            ],
            Sources::listFieldHandler()->schema(new Products(label: 'Products', max: 12, sources: ['bestsellers'], limit: 4)),
        );
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'minLength' => 1],
                'x-cms-options' => ['max' => null, 'sources' => [], 'limit' => 8],
            ],
            Sources::listFieldHandler()->schema(new Taxons(label: 'Taxons')),
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
        $list = Sources::listFieldHandler()->hydrate(
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

        $list = Sources::listFieldHandler()->hydrate(new Products(label: 'Products', limit: 2), ['C', 'A', 'B'], $this->hydrationContext(['product' => $products]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$products['C'], $products['A'], $products['B']], $list->toArray());
    }

    /**
     * @test
     */
    public function it_hydrates_taxons_from_the_taxon_references(): void
    {
        $shirts = self::taxon('T_SHIRTS');

        $list = Sources::listFieldHandler()->hydrate(
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
        $list = Sources::listFieldHandler()->hydrate(new Products(label: 'Products'), ['T_SHIRT'], $this->hydrationContext([]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertTrue($list->isEmpty());
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = Sources::listFieldHandler();
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
        $handler = Sources::listFieldHandler();
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
            [...Sources::listFieldHandler()->references(new Products(label: 'Products'), ['MUG', 'CAP', 'MUG', 7, ''])],
        );
        self::assertEquals(
            [new Reference('taxon', 'T_SHIRTS', false)],
            [...Sources::listFieldHandler()->references(new Taxons(label: 'Taxons'), ['T_SHIRTS'])],
        );
    }

    /**
     * @test
     */
    public function it_refers_to_nothing_when_the_value_is_not_a_list(): void
    {
        self::assertSame([], [...Sources::listFieldHandler()->references(new Products(label: 'Products'), 'MUG')]);
        self::assertSame([], [...Sources::listFieldHandler()->references(new Products(label: 'Products'), ['a' => 'MUG'])]);
    }

    /**
     * @test
     */
    public function it_accepts_a_list_of_codes_up_to_its_most_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = Sources::listFieldHandler();
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

        Sources::listFieldHandler()->validate(new Taxons(label: 'Taxons'), ['T_SHIRTS', 7], $context->reveal());
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

        Sources::listFieldHandler()->validate(new Products(label: 'Products', max: 1), ['MUG', 'CAP'], $context->reveal());
    }

    /**
     * @test
     */
    public function it_offers_the_sources_of_its_kind_that_are_not_disabled_in_its_schema_beside_hand_picking(): void
    {
        $handler = Sources::listFieldHandler(['on_sale' => new OnSale(), 'product_taxons' => new ProductTaxons(), 'clearance' => new Clearance()], ['clearance']);

        $schema = $handler->schema(new Products(label: 'Products', limit: 4));

        self::assertSame(['max' => null, 'sources' => ['on_sale'], 'limit' => 4], $schema['x-cms-options']);
        self::assertSame([
            ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1]],
            [
                'type' => 'object',
                'properties' => [
                    '$source' => ['type' => 'string', 'minLength' => 1],
                    '$params' => ['type' => 'object'],
                    '$limit' => ['type' => 'integer', 'minimum' => 1],
                ],
                'required' => ['$source', '$params', '$limit'],
                'additionalProperties' => false,
            ],
        ], $schema['anyOf']);
        self::assertSame(['product_taxons'], self::offered($handler->schema(new Taxons(label: 'Taxons'))));
    }

    /**
     * @test
     */
    public function it_offers_only_the_sources_its_sources_option_names_and_none_with_false(): void
    {
        $handler = Sources::listFieldHandler(['on_sale' => new OnSale(), 'clearance' => new Clearance()]);

        self::assertSame(['clearance'], self::offered($handler->schema(new Products(label: 'Products', sources: ['clearance', 'removed']))));

        $handPickedOnly = $handler->schema(new Products(label: 'Products', sources: false));
        self::assertSame([], self::offered($handPickedOnly));
        self::assertSame('array', $handPickedOnly['type'] ?? null);
        self::assertArrayNotHasKey('anyOf', $handPickedOnly);
    }

    /**
     * @test
     */
    public function it_resolves_the_source_only_when_the_list_is_first_read_and_shows_its_first_limit_items_without_counting(): void
    {
        $products = [self::product('A'), self::product('B'), self::product('C')];
        $source = new OnSale($products);
        $handler = Sources::listFieldHandler(['on_sale' => $source]);

        $list = $handler->hydrate(new Products(label: 'Products'), self::stored('on_sale', limit: 2), self::context());

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([], $source->resolvedWith, 'Nothing is resolved before the list is read');

        self::assertSame([$products[0], $products[1]], [...$list]);
        self::assertCount(2, $list);
        self::assertCount(1, $source->adapters);
        self::assertSame([[0, 2]], $source->adapters[0]->slices);
        self::assertFalse($source->adapters[0]->counted);
        self::assertSame($source->adapters[0], $list->adapter());
    }

    /**
     * @test
     */
    public function it_hands_the_source_its_parameters_hydrated_from_the_references_loaded_before_rendering(): void
    {
        $shirts = new Taxon();
        $shirts->setCode('T_SHIRTS');
        $source = new OnSale();
        $handler = Sources::listFieldHandler(['on_sale' => $source]);

        $list = $handler->hydrate(
            new Products(label: 'Products'),
            self::stored('on_sale', ['taxon' => 'T_SHIRTS', 'onlyNew' => true]),
            self::context(new ReferenceMap(['taxon' => ['T_SHIRTS' => $shirts]])),
        );
        self::assertInstanceOf(ItemList::class, $list);
        $list->toArray();

        self::assertCount(1, $source->resolvedWith);
        self::assertSame($shirts, $source->resolvedWith[0]->taxon);
        self::assertTrue($source->resolvedWith[0]->onlyNew);
    }

    /**
     * A taxon that was deleted, or that the shopper may no longer see, makes the source yield nothing rather than
     * widen to what it would yield without one, such as Bestsellers in every taxon
     *
     * @test
     */
    public function it_yields_nothing_for_a_source_whose_reference_parameter_was_set_but_no_longer_resolves(): void
    {
        $source = new OnSale([self::product('A')]);
        $handler = Sources::listFieldHandler(['on_sale' => $source]);

        $list = $handler->hydrate(new Products(label: 'Products'), self::stored('on_sale', ['taxon' => 'GONE']), self::context());

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([], $list->toArray());
        self::assertSame([], $source->resolvedWith, 'The source isn\'t asked');
    }

    /**
     * A reference parameter left empty is the source's to read, such as Bestsellers in every taxon
     *
     * @test
     */
    public function it_resolves_a_source_whose_reference_parameter_was_left_empty(): void
    {
        $source = new OnSale([self::product('A')]);
        $handler = Sources::listFieldHandler(['on_sale' => $source]);

        foreach ([[], ['taxon' => null], ['taxon' => '']] as $params) {
            $list = $handler->hydrate(new Products(label: 'Products'), self::stored('on_sale', $params), self::context());

            self::assertInstanceOf(ItemList::class, $list);
            self::assertCount(1, $list->toArray());
        }

        self::assertCount(3, $source->resolvedWith);
        self::assertNull($source->resolvedWith[0]->taxon);
    }

    /**
     * @test
     */
    public function it_reads_parameters_stored_as_an_empty_map(): void
    {
        $source = new OnSale([self::product('A')]);
        $list = Sources::listFieldHandler(['on_sale' => $source])->hydrate(new Products(label: 'Products'), ['$source' => 'on_sale', '$params' => new \stdClass(), '$limit' => 8], self::context());

        self::assertInstanceOf(ItemList::class, $list);
        self::assertCount(1, $list);
    }

    /**
     * @test
     */
    public function it_keeps_resolving_a_disabled_source(): void
    {
        $products = [self::product('A')];
        $list = Sources::listFieldHandler(['on_sale' => new OnSale($products)], ['on_sale'])->hydrate(new Products(label: 'Products'), self::stored('on_sale'), self::context());

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame($products, $list->toArray());
    }

    /**
     * @test
     */
    public function it_yields_nothing_for_a_source_the_code_no_longer_has(): void
    {
        $list = Sources::listFieldHandler()->hydrate(new Products(label: 'Products'), self::stored('removed'), self::context());

        self::assertInstanceOf(ItemList::class, $list);
        self::assertTrue($list->isEmpty());
    }

    /**
     * @test
     */
    public function it_yields_nothing_for_a_source_of_another_kind(): void
    {
        $handler = Sources::listFieldHandler(['product_taxons' => new ProductTaxons()]);

        $list = $handler->hydrate(new Products(label: 'Products'), self::stored('product_taxons'), self::context(hostPageContext: ['product' => self::product('A')]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertTrue($list->isEmpty());
    }

    /**
     * @test
     */
    public function it_yields_nothing_when_a_context_key_the_source_needs_is_missing(): void
    {
        $handler = Sources::listFieldHandler(['product_taxons' => new ProductTaxons()]);
        $field = new Taxons(label: 'Taxons');

        $without = $handler->hydrate($field, self::stored('product_taxons'), self::context());
        self::assertInstanceOf(ItemList::class, $without);
        self::assertTrue($without->isEmpty());

        $with = $handler->hydrate($field, self::stored('product_taxons'), self::context(hostPageContext: ['product' => self::product('A')]));
        self::assertInstanceOf(ItemList::class, $with);
        self::assertCount(1, $with);
    }

    /**
     * @test
     */
    public function it_fits_a_source_with_its_parameters_and_a_limit(): void
    {
        $handler = Sources::listFieldHandler(['on_sale' => new OnSale()]);
        $field = new Products(label: 'Products');

        self::assertTrue($handler->fits($field, self::stored('on_sale', ['taxon' => 'T_SHIRTS'])));
        self::assertTrue($handler->fits($field, ['$source' => 'on_sale', '$params' => new \stdClass(), '$limit' => 1]));
        // A source the code no longer has still fits: it's another problem
        self::assertTrue($handler->fits($field, self::stored('removed')));

        self::assertFalse($handler->fits($field, ['$source' => 'on_sale', '$params' => [], '$limit' => 0]));
        self::assertFalse($handler->fits($field, ['$source' => 'on_sale', '$params' => [], '$limit' => '8']));
        self::assertFalse($handler->fits($field, ['$source' => 'on_sale', '$params' => []]));
        self::assertFalse($handler->fits($field, ['$source' => '', '$params' => [], '$limit' => 8]));
        self::assertFalse($handler->fits($field, ['$source' => 'on_sale', '$params' => ['T_SHIRTS'], '$limit' => 8]));
        self::assertFalse($handler->fits($field, ['$source' => 'on_sale', '$params' => [], '$limit' => 8, 'extra' => true]));
    }

    /**
     * @test
     */
    public function it_refers_to_what_the_source_s_parameters_hold(): void
    {
        $handler = Sources::listFieldHandler(['on_sale' => new OnSale()]);

        self::assertEquals(
            [new Reference('taxon', 'T_SHIRTS', false)],
            [...$handler->references(new Products(label: 'Products'), self::stored('on_sale', ['taxon' => 'T_SHIRTS', 'onlyNew' => true]))],
        );
        self::assertSame([], [...$handler->references(new Products(label: 'Products'), self::stored('removed', ['taxon' => 'T_SHIRTS']))]);
        self::assertSame([], [...$handler->references(new Products(label: 'Products'), ['$source' => 'on_sale', '$limit' => 8])]);
    }

    /**
     * @test
     */
    public function it_accepts_a_source_of_its_kind_with_its_required_parameters_even_a_disabled_one(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $field = new Products(label: 'Products', max: 1);
        Sources::listFieldHandler(['on_sale' => new OnSale()])->validate($field, self::stored('on_sale', ['taxon' => 'T_SHIRTS'], limit: 12), $context->reveal());
        Sources::listFieldHandler(['on_sale' => new OnSale()], ['on_sale'])->validate($field, self::stored('on_sale', ['taxon' => 'T_SHIRTS']), $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_shaped_like_a_source_that_is_not_one(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_source', [], fn (ExecutionContextInterface $context) => Sources::listFieldHandler(['on_sale' => new OnSale()])->validate(new Products(label: 'Products'), ['$source' => 'on_sale', '$limit' => 0], $context));
    }

    /**
     * @test
     */
    public function it_rejects_a_source_its_sources_option_does_not_name(): void
    {
        $this->expectViolation('setono_sylius_cms.field.source_not_allowed', ['{{ source }}' => 'app.source.on_sale'], fn (ExecutionContextInterface $context) => Sources::listFieldHandler(['on_sale' => new OnSale(), 'clearance' => new Clearance()])->validate(new Products(label: 'Products', sources: ['clearance']), self::stored('on_sale', ['taxon' => 'T_SHIRTS']), $context));
    }

    /**
     * @test
     */
    public function it_rejects_every_source_when_its_sources_option_allows_only_hand_picking(): void
    {
        $this->expectViolation('setono_sylius_cms.field.source_not_allowed', ['{{ source }}' => 'app.source.on_sale'], fn (ExecutionContextInterface $context) => Sources::listFieldHandler(['on_sale' => new OnSale()], ['on_sale'])->validate(new Products(label: 'Products', sources: false), self::stored('on_sale', ['taxon' => 'T_SHIRTS']), $context));
    }

    /**
     * A field narrowed in the code after content was made keeps showing what that content holds, as a disabled source
     * does, until an editor changes it; publish refuses it
     *
     * @test
     */
    public function it_keeps_resolving_a_source_its_sources_option_no_longer_allows(): void
    {
        $products = [self::product('A')];
        $list = Sources::listFieldHandler(['on_sale' => new OnSale($products)])->hydrate(new Products(label: 'Products', sources: false), self::stored('on_sale'), self::context());

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame($products, $list->toArray());
    }

    /**
     * @test
     */
    public function it_rejects_a_source_the_code_no_longer_has(): void
    {
        $this->expectViolation('setono_sylius_cms.field.unknown_source', ['{{ source }}' => 'removed'], fn (ExecutionContextInterface $context) => Sources::listFieldHandler()->validate(new Products(label: 'Products'), self::stored('removed'), $context));
    }

    /**
     * @test
     */
    public function it_rejects_a_source_of_another_kind(): void
    {
        $this->expectViolation('setono_sylius_cms.field.source_of_another_kind', ['{{ source }}' => 'app.source.product_taxons'], fn (ExecutionContextInterface $context) => Sources::listFieldHandler(['product_taxons' => new ProductTaxons()])->validate(new Products(label: 'Products'), self::stored('product_taxons'), $context));
    }

    /**
     * @test
     */
    public function it_rejects_a_source_without_a_required_parameter(): void
    {
        $this->expectViolation('setono_sylius_cms.field.source_parameter_required', ['{{ parameter }}' => 'app.source.on_sale.taxon'], fn (ExecutionContextInterface $context) => Sources::listFieldHandler(['on_sale' => new OnSale()])->validate(new Products(label: 'Products'), self::stored('on_sale', ['onlyNew' => true]), $context));
    }

    /**
     * @test
     */
    public function it_rejects_a_parameter_its_field_type_rejects(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_taxon', [], fn (ExecutionContextInterface $context) => Sources::listFieldHandler(['on_sale' => new OnSale()])->validate(new Products(label: 'Products'), self::stored('on_sale', ['taxon' => 7]), $context));
    }

    /**
     * The codes of the sources a list field's schema says it offers
     *
     * @param array<string, mixed> $schema
     */
    private static function offered(array $schema): mixed
    {
        $options = $schema['x-cms-options'] ?? null;
        self::assertIsArray($options);

        return $options['sources'] ?? null;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{'$source': string, '$params': array<string, mixed>, '$limit': int}
     */
    private static function stored(string $code, array $params = [], int $limit = 8): array
    {
        return ['$source' => $code, '$params' => $params, '$limit' => $limit];
    }

    /**
     * @param array<string, object> $hostPageContext
     */
    private static function context(ReferenceMap $references = new ReferenceMap(), array $hostPageContext = []): HydrationContext
    {
        return Rendering::hydrationContext(Rendering::context(hostPageContext: $hostPageContext), $references);
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
            Rendering::context(),
            new ReferenceMap($records),
        );
    }
}
