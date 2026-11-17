<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Binding\PropertyPathProperty;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBindablePropertiesPass;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\MissingParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\NotABindableProperty;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\SameBindingAsBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevel;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevelParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\WarehouseName;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Bindable properties are services carrying #[AsBindableProperty], or declared in setono_sylius_cms.bindable_properties,
 * handed to the registry by binding. See "Bindable properties" in docs/spec/extending.md
 */
final class RegisterBindablePropertiesPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(BindablePropertyRegistry::class, new Definition(BindablePropertyRegistry::class, [[], null, []]));
        $this->setParameter('setono_sylius_cms.context_keys', ['product' => [], 'taxon' => [], 'entry' => []]);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterBindablePropertiesPass());
    }

    /**
     * @test
     */
    public function it_hands_the_properties_to_the_registry_by_binding_with_their_declarations(): void
    {
        $this->setProperty('app.brand', ProductBrand::class);
        $this->setProperty('app.stock_level', StockLevel::class);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BindablePropertyRegistry::class, 0, [
            'product.brand' => ['context_key' => 'product', 'code' => 'brand', 'label' => 'app.binding.brand', 'type' => 'text', 'params' => null],
            'product.stock_level' => ['context_key' => 'product', 'code' => 'stock_level', 'label' => 'app.binding.stock_level', 'type' => 'number', 'params' => StockLevelParams::class],
        ]);
        self::assertEquals([
            'product.brand' => new ServiceClosureArgument(new Reference('app.brand')),
            'product.stock_level' => new ServiceClosureArgument(new Reference('app.stock_level')),
        ], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_reads_the_declaration_of_a_property_path_from_its_tag(): void
    {
        $this->setDefinition('setono_sylius_cms.bindable_property.product.material', (new Definition(PropertyPathProperty::class, ['material']))->addTag('setono_sylius_cms.bindable_property', [
            'context_key' => 'product',
            'code' => 'material',
            'label' => 'app.binding.material',
            'type' => 'text',
        ]));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BindablePropertyRegistry::class, 0, [
            'product.material' => ['context_key' => 'product', 'code' => 'material', 'label' => 'app.binding.material', 'type' => 'text', 'params' => null],
        ]);
    }

    /**
     * @test
     */
    public function it_orders_the_properties_by_their_tag_s_priority_and_else_as_they_are_defined(): void
    {
        $this->setProperty('app.stock_level', StockLevel::class);
        $this->setDefinition('app.brand', (new Definition(ProductBrand::class))->addTag('setono_sylius_cms.bindable_property', ['priority' => 10]));

        $this->compile();

        self::assertSame(['product.brand', 'product.stock_level'], array_keys($this->declarations()));
    }

    /**
     * @test
     */
    public function it_accepts_the_channel_and_the_customer_without_declared_context_keys(): void
    {
        $this->setDefinition('app.channel_slogan', (new Definition(PropertyPathProperty::class, ['slogan']))->addTag('setono_sylius_cms.bindable_property', [
            'context_key' => 'channel', 'code' => 'slogan', 'label' => 'app.binding.slogan', 'type' => 'text',
        ]));
        $this->setDefinition('app.customer_nickname', (new Definition(PropertyPathProperty::class, ['nickname']))->addTag('setono_sylius_cms.bindable_property', [
            'context_key' => 'customer', 'code' => 'nickname', 'label' => 'app.binding.nickname', 'type' => 'text',
        ]));

        $this->compile();

        self::assertSame(['channel.slogan', 'customer.nickname'], array_keys($this->declarations()));
    }

    /**
     * @test
     */
    public function it_accepts_one_property_registered_twice(): void
    {
        $this->setProperty('app.brand', ProductBrand::class);
        $this->setProperty(ProductBrand::class, ProductBrand::class);

        $this->compile();

        self::assertSame(['product.brand'], array_keys($this->declarations()));
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_properties_with_the_same_binding(): void
    {
        $this->setProperty('app.brand', ProductBrand::class);
        $this->setProperty('app.other', SameBindingAsBrand::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The bindable property "product.brand" is declared twice, by %s and by %s', ProductBrand::class, SameBindingAsBrand::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_property_that_reads_an_undeclared_context_key(): void
    {
        $this->setProperty('app.warehouse', WarehouseName::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The bindable property %s needs the context key "warehouse", which isn\'t declared under setono_sylius_cms.context_keys', WarehouseName::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_an_invalid_code(): void
    {
        $this->setProperty('app.invalid', InvalidCode::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The code "Brand" of the bindable property %s must match /^[a-z][a-z0-9_]*$/', InvalidCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_class_that_does_not_implement_the_interface(): void
    {
        $this->setProperty('app.weight', NotABindableProperty::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The bindable property %s must implement Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface', NotABindableProperty::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_parameters_class_that_does_not_exist(): void
    {
        $this->setProperty('app.colour', MissingParams::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The parameters class App\Binding\ColourParams of the bindable property %s doesn\'t exist', MissingParams::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_tagged_class_without_the_attribute(): void
    {
        $this->setProperty('app.params', StockLevelParams::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.params" is tagged "setono_sylius_cms.bindable_property", but its class %s has no #[AsBindableProperty]', StockLevelParams::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_type_that_is_no_binding_type(): void
    {
        $this->setDefinition('app.colour', (new Definition(PropertyPathProperty::class, ['colour']))->addTag('setono_sylius_cms.bindable_property', [
            'context_key' => 'product', 'code' => 'colour', 'label' => 'app.binding.colour', 'type' => 'image',
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The bindable property %s has the type "image", which is none of text, number, url', PropertyPathProperty::class));

        $this->compile();
    }

    /**
     * @param class-string $class
     */
    private function setProperty(string $id, string $class): void
    {
        $this->setDefinition($id, (new Definition($class))->addTag('setono_sylius_cms.bindable_property'));
    }

    /**
     * @return array<mixed>
     */
    private function declarations(): array
    {
        $declarations = $this->container->getDefinition(BindablePropertyRegistry::class)->getArgument(0);
        self::assertIsArray($declarations);

        return $declarations;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function locatorArgument(): array
    {
        $locator = $this->container->getDefinition(BindablePropertyRegistry::class)->getArgument(2);
        self::assertInstanceOf(Reference::class, $locator);

        $definition = $this->container->getDefinition((string) $locator);
        self::assertSame(ServiceLocator::class, $definition->getClass());

        $services = $definition->getArgument(0);
        self::assertIsArray($services);

        return $services;
    }
}
