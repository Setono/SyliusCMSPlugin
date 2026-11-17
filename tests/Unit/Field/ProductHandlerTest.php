<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Product;
use Setono\SyliusCMSPlugin\Field\ProductHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithCatalog;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product as ProductModel;
use Sylius\Component\Core\Model\Taxon as TaxonModel;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

/**
 * The Product field type: a product's code, hydrated as the product while the shopper may see it
 */
final class ProductHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_product_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithCatalog::class);

        self::assertSame('product', $fields['product']->type->code);
        self::assertFalse($fields['product']->translatable);
        self::assertSame(ProductHandler::class, $fields['product']->handler());
        self::assertInstanceOf(Product::class, $fields['product']->field);
    }

    /**
     * @test
     */
    public function it_describes_a_code_in_its_schema(): void
    {
        self::assertSame(['type' => 'string', 'minLength' => 1], (new ProductHandler())->schema(new Product(label: 'Product')));
    }

    /**
     * @test
     */
    public function it_hydrates_the_code_as_the_product_the_shopper_may_see(): void
    {
        $mug = new ProductModel();
        $mug->setCode('MUG');
        $context = $this->hydrationContext(['product' => ['MUG' => $mug, 'CAP' => new TaxonModel()]]);

        $handler = new ProductHandler();
        self::assertSame($mug, $handler->hydrate(new Product(label: 'Product'), 'MUG', $context));
        self::assertNull($handler->hydrate(new Product(label: 'Product'), 'T_SHIRT', $context), 'A product the shopper may not see, or that was deleted, is missing');
        self::assertNull($handler->hydrate(new Product(label: 'Product'), 'CAP', $context), 'Only a product is a product');
        self::assertNull($handler->hydrate(new Product(label: 'Product'), ['MUG'], $context));
    }

    /**
     * @test
     */
    public function it_fits_a_code(): void
    {
        $handler = new ProductHandler();

        self::assertTrue($handler->fits(new Product(label: 'Product'), 'MUG'));
        self::assertFalse($handler->fits(new Product(label: 'Product'), ''));
        self::assertFalse($handler->fits(new Product(label: 'Product'), 7));
        self::assertFalse($handler->fits(new Product(label: 'Product'), ['MUG']));
    }

    /**
     * @test
     */
    public function it_refers_to_the_product_as_a_link_that_an_export_leaves_as_a_code(): void
    {
        $handler = new ProductHandler();

        self::assertEquals([new Reference('product', 'MUG', false)], [...$handler->references(new Product(label: 'Product'), 'MUG')]);
        self::assertSame([], [...$handler->references(new Product(label: 'Product'), ['MUG'])]);
    }

    /**
     * @test
     */
    public function it_accepts_a_code_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        (new ProductHandler())->validate(new Product(label: 'Product'), 'MUG', $context->reveal());
        (new ProductHandler())->validate(new Product(label: 'Product'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_code(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_product')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new ProductHandler())->validate(new Product(label: 'Product'), ['MUG'], $context->reveal());
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
