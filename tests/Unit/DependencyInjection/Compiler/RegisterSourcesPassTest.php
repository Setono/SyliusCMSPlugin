<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterSourcesPass;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\Clearance;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\MissingParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\NotASource;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSaleParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\ProductTaxons;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\SameCodeAsOnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\UnknownOf;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Sources are services carrying #[AsSource], handed to the registry by code. See "Sources" in docs/spec/extending.md
 */
final class RegisterSourcesPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(SourceRegistry::class, new Definition(SourceRegistry::class, [[], null, [], []]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterSourcesPass());
    }

    /**
     * @test
     */
    public function it_hands_the_sources_to_the_registry_by_code(): void
    {
        $this->setSource('app.on_sale', OnSale::class);
        $this->setParameter('app.product_taxons.class', ProductTaxons::class);
        $this->setSource('app.product_taxons', '%app.product_taxons.class%');

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(SourceRegistry::class, 0, [
            'on_sale' => OnSale::class,
            'product_taxons' => ProductTaxons::class,
        ]);
        self::assertEquals([
            'on_sale' => new ServiceClosureArgument(new Reference('app.on_sale')),
            'product_taxons' => new ServiceClosureArgument(new Reference('app.product_taxons')),
        ], $this->locatorArgument());
    }

    /**
     * A bundle's services are defined after an app's, so the shipped sources give their tag a priority to come first
     *
     * @test
     */
    public function it_orders_the_sources_by_their_tag_s_priority_and_else_as_they_are_defined(): void
    {
        $this->setSource('app.product_taxons', ProductTaxons::class);
        $this->setSource('app.clearance', Clearance::class);
        $this->setDefinition('app.on_sale', (new Definition(OnSale::class))->addTag('setono_sylius_cms.source', ['priority' => 10]));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(SourceRegistry::class, 0, [
            'on_sale' => OnSale::class,
            'product_taxons' => ProductTaxons::class,
            'clearance' => Clearance::class,
        ]);
    }

    /**
     * @test
     */
    public function it_accepts_one_source_registered_twice(): void
    {
        $this->setSource('app.on_sale', OnSale::class);
        $this->setSource(OnSale::class, OnSale::class);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(SourceRegistry::class, 0, ['on_sale' => OnSale::class]);
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_sources_with_the_same_code(): void
    {
        $this->setSource('app.on_sale', OnSale::class);
        $this->setSource('app.other', SameCodeAsOnSale::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The source code "on_sale" is used by both %s and %s. Codes are unique per kind, so give one of them another code', OnSale::class, SameCodeAsOnSale::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_tagged_class_without_the_attribute(): void
    {
        $this->setSource('app.params', OnSaleParams::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.params" is tagged "setono_sylius_cms.source", but its class %s has no #[AsSource]', OnSaleParams::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_an_invalid_code(): void
    {
        $this->setSource('app.invalid', InvalidCode::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The source code "On-Sale" of %s must match /^[a-z][a-z0-9_]*$/', InvalidCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_source_that_does_not_implement_the_interface(): void
    {
        $this->setSource('app.not_a_source', NotASource::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The source %s must implement %s', NotASource::class, SourceInterface::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_parameters_class_that_does_not_exist(): void
    {
        $this->setSource('app.missing_params', MissingParams::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The parameters class App\Source\MissingParams of the source %s doesn\'t exist', MissingParams::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_source_that_lists_a_class_that_does_not_exist(): void
    {
        $this->setSource('app.unknown_of', UnknownOf::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The source %s lists App\Entity\Recipe, which is no class or interface', UnknownOf::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_source_that_needs_a_context_key_the_config_does_not_declare(): void
    {
        $this->setParameter('setono_sylius_cms.context_keys', ['taxon' => ['class' => TaxonInterface::class, 'label' => 'setono_sylius_cms.context_key.taxon']]);
        $this->setSource('app.product_taxons', ProductTaxons::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The source %s needs the context key "product", which isn\'t declared under setono_sylius_cms.context_keys', ProductTaxons::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(SourceRegistry::class);
        $this->setSource('app.not_a_source', NotASource::class);

        $this->compile();

        $this->assertContainerBuilderNotHasService(SourceRegistry::class);
    }

    private function setSource(string $id, string $class): void
    {
        $this->setDefinition($id, (new Definition($class))->addTag('setono_sylius_cms.source'));
    }

    /**
     * @return array<mixed>
     */
    private function locatorArgument(): array
    {
        $argument = $this->container->getDefinition(SourceRegistry::class)->getArgument(2);
        self::assertInstanceOf(Reference::class, $argument);

        $locator = $this->container->getDefinition((string) $argument);
        self::assertSame(ServiceLocator::class, $locator->getClass());

        $sources = $locator->getArgument(0);
        self::assertIsArray($sources);

        return $sources;
    }
}
