<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Setono\SyliusCMSPlugin\Context\HostPageContextResolver;
use Setono\SyliusCMSPlugin\Context\ProductContextResolver;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContextResolversPass;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\BrandResolver;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\NotAResolver;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\OtherStoreLocationResolver;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\ProjectProductResolver;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\StoreLocationResolver;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Context resolvers are services carrying #[AsContextResolver], handed to the slots' context by key. See "Context keys
 * and resolvers" in docs/spec/extending.md
 */
final class RegisterContextResolversPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setParameter('setono_sylius_cms.context_keys', [
            'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
            'entry' => ['class' => EntryInterface::class, 'label' => 'setono_sylius_cms.context_key.entry'],
            'store_location' => ['class' => \stdClass::class, 'label' => 'app.cms.context_key.store_location'],
        ]);
        $this->setDefinition(HostPageContextResolver::class, new Definition(HostPageContextResolver::class, [null, [], null]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterContextResolversPass());
    }

    /**
     * @test
     */
    public function it_hands_the_resolvers_to_the_host_page_context_by_key(): void
    {
        $this->setResolver(ProductContextResolver::class, ProductContextResolver::class, -100);
        $this->setResolver('app.store_location_resolver', StoreLocationResolver::class);

        $this->compile();

        self::assertEquals([
            'product' => new ServiceClosureArgument(new Reference(ProductContextResolver::class)),
            'store_location' => new ServiceClosureArgument(new Reference('app.store_location_resolver')),
        ], $this->locatorArgument());
    }

    /**
     * A project's resolver replaces a shipped one, whose tag has a lower priority
     *
     * @test
     */
    public function it_takes_the_resolver_whose_tag_has_the_highest_priority_for_a_key(): void
    {
        $this->setResolver('app.product_resolver', ProjectProductResolver::class);
        $this->setResolver(ProductContextResolver::class, ProductContextResolver::class, -100);

        $this->compile();

        self::assertEquals(['product' => new ServiceClosureArgument(new Reference('app.product_resolver'))], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_resolvers_of_one_key_at_the_same_priority(): void
    {
        $this->setResolver('app.store_location_resolver', StoreLocationResolver::class);
        $this->setResolver('app.other_store_location_resolver', OtherStoreLocationResolver::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('Both %s and %s resolve the context key "store_location". Give the one that replaces the other a higher priority', StoreLocationResolver::class, OtherStoreLocationResolver::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_resolver_of_a_context_key_the_config_does_not_declare(): void
    {
        $this->setResolver('app.brand_resolver', BrandResolver::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The context resolver %s resolves the context key "brand", which isn\'t declared under setono_sylius_cms.context_keys', BrandResolver::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_tagged_class_without_the_attribute(): void
    {
        $this->setResolver('app.not_tagged_right', \stdClass::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The service "app.not_tagged_right" is tagged "setono_sylius_cms.context_resolver", but its class stdClass has no #[AsContextResolver]');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_resolver_that_does_not_implement_the_interface(): void
    {
        $this->setResolver('app.not_a_resolver', NotAResolver::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The context resolver %s must implement %s', NotAResolver::class, ContextResolverInterface::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_host_page_context(): void
    {
        $this->container->removeDefinition(HostPageContextResolver::class);
        $this->setResolver('app.not_a_resolver', NotAResolver::class);

        $this->compile();

        $this->assertContainerBuilderNotHasService(HostPageContextResolver::class);
    }

    private function setResolver(string $id, string $class, ?int $priority = null): void
    {
        $this->setDefinition($id, (new Definition($class))->addTag('setono_sylius_cms.context_resolver', null === $priority ? [] : ['priority' => $priority]));
    }

    /**
     * @return array<mixed>
     */
    private function locatorArgument(): array
    {
        $argument = $this->container->getDefinition(HostPageContextResolver::class)->getArgument(1);
        self::assertInstanceOf(Reference::class, $argument);

        $locator = $this->container->getDefinition((string) $argument);
        self::assertSame(ServiceLocator::class, $locator->getClass());

        $resolvers = $locator->getArgument(0);
        self::assertIsArray($resolvers);

        return $resolvers;
    }
}
