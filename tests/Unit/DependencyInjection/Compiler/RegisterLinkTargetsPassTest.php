<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Builder\Search\ChoicesSearch;
use Setono\SyliusCMSPlugin\Builder\Search\LinkTargetSearch;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLinkTargetsPass;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\Target\UrlLinkTarget;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\NotATarget;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\RecipeLinkTarget;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\SameCodeAsStore;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\StoreLinkTarget;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RegisterLinkTargetsPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(LinkTargetRegistry::class, new Definition(LinkTargetRegistry::class, [[], null]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterLinkTargetsPass());
    }

    /**
     * @test
     */
    public function it_hands_the_link_targets_to_the_registry_by_code_in_the_order_they_are_registered(): void
    {
        $this->setTarget('app.store', StoreLinkTarget::class);
        $this->setParameter('app.url.class', UrlLinkTarget::class);
        $this->setTarget(UrlLinkTarget::class, '%app.url.class%');

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LinkTargetRegistry::class, 0, [
            'app_store' => StoreLinkTarget::class,
            'url' => UrlLinkTarget::class,
        ]);
        self::assertEquals([
            'app_store' => new ServiceClosureArgument(new Reference('app.store')),
            'url' => new ServiceClosureArgument(new Reference(UrlLinkTarget::class)),
        ], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_hands_them_by_the_priority_of_their_tag_highest_first(): void
    {
        $this->setTarget('app.store', StoreLinkTarget::class);
        $this->setDefinition(UrlLinkTarget::class, (new Definition(UrlLinkTarget::class))->addTag('setono_sylius_cms.link_target', ['priority' => 10]));
        $this->setTarget('app.recipe', RecipeLinkTarget::class);

        $this->compile();

        self::assertSame(['url', 'app_store', 'recipe'], array_keys((array) $this->container->getDefinition(LinkTargetRegistry::class)->getArgument(0)));
    }

    /**
     * @test
     */
    public function it_makes_the_search_endpoint_search_a_link_target_under_its_code(): void
    {
        $this->setTarget('app.store', StoreLinkTarget::class);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LinkTargetSearch::class . '.app_store', 0, new Reference('app.store'));
        $this->assertContainerBuilderHasServiceDefinitionWithTag(LinkTargetSearch::class . '.app_store', 'setono_sylius_cms.search', ['kind' => 'app_store']);
    }

    /**
     * @test
     */
    public function it_leaves_a_link_target_to_the_search_of_its_kind_when_there_is_one(): void
    {
        $this->setTarget('app.recipe', RecipeLinkTarget::class);
        $this->setDefinition('app.recipe_search', (new Definition(ChoicesSearch::class))->addTag('setono_sylius_cms.search', ['kind' => 'recipe']));

        $this->compile();

        $this->assertContainerBuilderNotHasService(LinkTargetSearch::class . '.recipe');
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_link_targets_with_the_same_code(): void
    {
        $this->setTarget('app.store', StoreLinkTarget::class);
        $this->setTarget('app.other', SameCodeAsStore::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The link target code "app_store" is used by both %s and %s. Codes are unique per kind, so give one of them another code', StoreLinkTarget::class, SameCodeAsStore::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_tagged_class_without_the_attribute(): void
    {
        $this->setTarget('app.recipe', Recipe::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.recipe" is tagged "setono_sylius_cms.link_target", but its class %s has no #[AsLinkTarget]', Recipe::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_an_invalid_code(): void
    {
        $this->setTarget('app.invalid', InvalidCode::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The link target code "Store-Locator" of %s must match /^[a-z][a-z0-9_]*$/', InvalidCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_target_that_does_not_implement_the_interface(): void
    {
        $this->setTarget('app.not_a_target', NotATarget::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The link target %s must implement %s', NotATarget::class, LinkTargetInterface::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(LinkTargetRegistry::class);
        $this->setTarget('app.not_a_target', NotATarget::class);

        $this->compile();

        $this->assertContainerBuilderNotHasService(LinkTargetRegistry::class);
    }

    private function setTarget(string $id, string $class): void
    {
        $this->setDefinition($id, (new Definition($class))->addTag('setono_sylius_cms.link_target'));
    }

    /**
     * @return array<mixed>
     */
    private function locatorArgument(): array
    {
        $argument = $this->container->getDefinition(LinkTargetRegistry::class)->getArgument(1);
        self::assertInstanceOf(Reference::class, $argument);

        $locator = $this->container->getDefinition((string) $argument);
        self::assertSame(ServiceLocator::class, $locator->getClass());

        $targets = $locator->getArgument(0);
        self::assertIsArray($targets);

        return $targets;
    }
}
