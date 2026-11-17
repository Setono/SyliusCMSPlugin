<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownSourceException;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSaleParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\ProductTaxons;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The sources by code, as their #[AsSource] declares them, with the fields of their parameters classes. See "Sources" in
 * docs/spec/extending.md
 */
final class SourceRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_source_s_attribute_and_the_fields_of_its_parameters_class(): void
    {
        $source = new OnSale();
        $metadata = self::registry(['on_sale' => $source])->get('on_sale');

        self::assertSame('on_sale', $metadata->code);
        self::assertSame('app.source.on_sale', $metadata->label);
        self::assertSame(ProductInterface::class, $metadata->of);
        self::assertSame([], $metadata->needs);
        self::assertSame(OnSaleParams::class, $metadata->params);
        self::assertSame(['taxon', 'onlyNew'], array_keys($metadata->fields));
        self::assertInstanceOf(Taxon::class, $metadata->fields['taxon']->field);
        self::assertInstanceOf(Toggle::class, $metadata->fields['onlyNew']->field);
        self::assertFalse($metadata->disabled);
        self::assertSame($source, $metadata->source);
    }

    /**
     * @test
     */
    public function it_gives_a_source_without_parameters_no_fields_and_keeps_what_it_needs(): void
    {
        $metadata = self::registry(['product_taxons' => new ProductTaxons()])->get('product_taxons');

        self::assertSame(TaxonInterface::class, $metadata->of);
        self::assertSame(['product'], $metadata->needs);
        self::assertNull($metadata->params);
        self::assertSame([], $metadata->fields);
    }

    /**
     * @test
     */
    public function it_marks_a_disabled_source_and_keeps_it(): void
    {
        $registry = self::registry(['on_sale' => new OnSale(), 'product_taxons' => new ProductTaxons()], ['on_sale']);

        self::assertTrue($registry->has('on_sale'));
        self::assertTrue($registry->get('on_sale')->disabled);
        self::assertFalse($registry->get('product_taxons')->disabled);
    }

    /**
     * @test
     */
    public function it_reads_each_source_once(): void
    {
        $registry = self::registry(['on_sale' => new OnSale()]);

        self::assertSame($registry->get('on_sale'), $registry->get('on_sale'));
    }

    /**
     * @test
     */
    public function it_lists_every_source_in_the_order_it_got_them(): void
    {
        $registry = self::registry(['product_taxons' => new ProductTaxons(), 'on_sale' => new OnSale()]);

        self::assertFalse($registry->has('removed'));
        self::assertSame(['product_taxons', 'on_sale'], array_map(static fn (SourceMetadata $source): string => $source->code, $registry->all()));
    }

    /**
     * @test
     */
    public function it_throws_for_a_source_the_code_does_not_have(): void
    {
        $this->expectException(UnknownSourceException::class);
        $this->expectExceptionMessage('There is no source with the code "removed"');

        self::registry([])->get('removed');
    }

    /**
     * @param array<string, SourceInterface> $sources
     * @param list<string> $disabled
     */
    private static function registry(array $sources, array $disabled = []): SourceRegistry
    {
        return new SourceRegistry(
            array_map(static fn (SourceInterface $source): string => $source::class, $sources),
            new FieldReader(),
            new ServiceLocator(array_map(static fn (SourceInterface $source): \Closure => static fn (): SourceInterface => $source, $sources)),
            $disabled,
        );
    }
}
