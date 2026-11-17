<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\TaxonHandler;
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
 * The Taxon field type: a taxon's code, hydrated as the taxon while the shopper may see it
 */
final class TaxonHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_taxon_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithCatalog::class);

        self::assertSame('taxon', $fields['taxon']->type->code);
        self::assertFalse($fields['taxon']->translatable);
        self::assertSame(TaxonHandler::class, $fields['taxon']->handler());
        self::assertInstanceOf(Taxon::class, $fields['taxon']->field);
    }

    /**
     * @test
     */
    public function it_describes_a_code_in_its_schema(): void
    {
        self::assertSame(['type' => 'string', 'minLength' => 1], (new TaxonHandler())->schema(new Taxon(label: 'Taxon')));
    }

    /**
     * @test
     */
    public function it_hydrates_the_code_as_the_taxon_the_shopper_may_see(): void
    {
        $shirts = new TaxonModel();
        $shirts->setCode('T_SHIRTS');
        $context = $this->hydrationContext(['taxon' => ['T_SHIRTS' => $shirts, 'MUG' => new ProductModel()]]);

        $handler = new TaxonHandler();
        self::assertSame($shirts, $handler->hydrate(new Taxon(label: 'Taxon'), 'T_SHIRTS', $context));
        self::assertNull($handler->hydrate(new Taxon(label: 'Taxon'), 'CAPS', $context), 'A taxon the shopper may not see, or that was deleted, is missing');
        self::assertNull($handler->hydrate(new Taxon(label: 'Taxon'), 'MUG', $context), 'Only a taxon is a taxon');
        self::assertNull($handler->hydrate(new Taxon(label: 'Taxon'), ['T_SHIRTS'], $context));
    }

    /**
     * @test
     */
    public function it_fits_a_code(): void
    {
        $handler = new TaxonHandler();

        self::assertTrue($handler->fits(new Taxon(label: 'Taxon'), 'T_SHIRTS'));
        self::assertFalse($handler->fits(new Taxon(label: 'Taxon'), ''));
        self::assertFalse($handler->fits(new Taxon(label: 'Taxon'), ['T_SHIRTS']));
    }

    /**
     * @test
     */
    public function it_refers_to_the_taxon_as_a_link_that_an_export_leaves_as_a_code(): void
    {
        $handler = new TaxonHandler();

        self::assertEquals([new Reference('taxon', 'T_SHIRTS', false)], [...$handler->references(new Taxon(label: 'Taxon'), 'T_SHIRTS')]);
        self::assertSame([], [...$handler->references(new Taxon(label: 'Taxon'), 7)]);
    }

    /**
     * @test
     */
    public function it_accepts_a_code_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        (new TaxonHandler())->validate(new Taxon(label: 'Taxon'), 'T_SHIRTS', $context->reveal());
        (new TaxonHandler())->validate(new Taxon(label: 'Taxon'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_code(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_taxon')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new TaxonHandler())->validate(new Taxon(label: 'Taxon'), 7, $context->reveal());
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
