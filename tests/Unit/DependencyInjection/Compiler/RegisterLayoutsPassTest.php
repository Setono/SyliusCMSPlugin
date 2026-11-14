<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLayoutsPass;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\InvalidLayoutCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\LayoutOfAPlainClass;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\RecipeLanding;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\SecondLanding;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterLayoutsPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(LayoutRegistry::class, new Definition(LayoutRegistry::class, [[], new Reference(FieldReader::class)]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterLayoutsPass());
    }

    /**
     * @test
     */
    public function it_hands_the_tagged_layouts_to_the_registry_by_content_type_and_code_and_removes_their_services(): void
    {
        $this->setDefinition('app.landing', (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition(RecipeLanding::class, (new Definition())->addTag('setono_sylius_cms.layout'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LayoutRegistry::class, 0, ['page' => ['landing' => Landing::class], 'recipe' => ['landing' => RecipeLanding::class]]);
        $this->assertContainerBuilderNotHasService('app.landing');
        $this->assertContainerBuilderNotHasService(RecipeLanding::class);
    }

    /**
     * @test
     */
    public function it_resolves_a_class_given_as_a_parameter_and_accepts_one_layout_registered_twice(): void
    {
        $this->setParameter('app.landing.class', Landing::class);
        $this->setDefinition('app.landing', (new Definition('%app.landing.class%'))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition(Landing::class, (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LayoutRegistry::class, 0, ['page' => ['landing' => Landing::class]]);
    }

    /**
     * @test
     */
    public function two_layouts_of_one_content_type_with_the_same_code_fail_the_build(): void
    {
        $this->setDefinition('app.landing', (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition('app.second_landing', (new Definition(SecondLanding::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The layout code "landing" of the content type "page" is used by both %s and %s', Landing::class, SecondLanding::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_layout_code_must_be_a_valid_code(): void
    {
        $this->setDefinition('app.invalid', (new Definition(InvalidLayoutCode::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The layout code "Invalid-Code" of %s must match /^[a-z][a-z0-9_]*$/', InvalidLayoutCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_layouts_content_type_must_be_a_content_type_class(): void
    {
        $this->setDefinition('app.plain', (new Definition(LayoutOfAPlainClass::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The layout %s belongs to stdClass, which is no content type class', LayoutOfAPlainClass::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_tagged_class_must_carry_the_attribute(): void
    {
        $this->setDefinition('app.plain', (new Definition(Plain::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.plain" is tagged "setono_sylius_cms.layout", but its class %s has no #[Layout]', Plain::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_tagged_service_must_have_an_existing_class(): void
    {
        $this->setDefinition('app.missing', (new Definition('App\Missing'))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The layout service "app.missing" must have an existing class');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(LayoutRegistry::class);
        $this->setDefinition('app.landing', (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));

        $this->compile();

        $this->assertContainerBuilderHasService('app.landing');
    }
}
