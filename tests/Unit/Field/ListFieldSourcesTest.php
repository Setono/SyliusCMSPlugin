<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\Taxons;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\Clearance;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\ProductTaxons;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

/**
 * A list field filled by a source: stored as {"$source", "$params", "$limit"}, resolved when a template first reads it,
 * showing the source's first $limit items. See "List fields" and "The contract" in docs/spec/sources.md
 */
final class ListFieldSourcesTest extends TestCase
{
    use ProphecyTrait;

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
     * @param array<string, string> $parameters
     * @param \Closure(ExecutionContextInterface): void $validate
     */
    private function expectViolation(string $message, array $parameters, \Closure $validate): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        foreach ($parameters as $name => $value) {
            $builder->setParameter($name, $value)->willReturn($builder->reveal())->shouldBeCalledOnce();
        }
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation($message)->willReturn($builder->reveal())->shouldBeCalledOnce();

        $validate($context->reveal());
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

    private static function product(string $code): ProductInterface
    {
        $product = new Product();
        $product->setCode($code);

        return $product;
    }

    /**
     * @param array<string, object> $hostPageContext
     */
    private static function context(ReferenceMap $references = new ReferenceMap(), array $hostPageContext = []): HydrationContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return new HydrationContext(Rendering::context(channel: $channel, hostPageContext: $hostPageContext), $references);
    }
}
