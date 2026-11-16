<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLayoutsPass;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\InvalidLayoutCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\LayoutOfAPlainClass;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Post;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\PostLanding;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\RecipeLanding;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\SecondLanding;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Wide;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterLayoutsPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(LayoutRegistry::class, new Definition(LayoutRegistry::class, [[], new Reference(FieldReader::class), '%setono_sylius_cms.default_layouts%', '%setono_sylius_cms.disabled.layouts%']));
        $this->setParameter('setono_sylius_cms.default_layouts', []);
        $this->setParameter('setono_sylius_cms.disabled.layouts', []);
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
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition('app.landing', (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition(PostLanding::class, (new Definition())->addTag('setono_sylius_cms.layout'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LayoutRegistry::class, 0, ['page' => ['standard' => Standard::class, 'landing' => Landing::class], 'post' => ['landing' => PostLanding::class]]);
        $this->assertContainerBuilderNotHasService('app.standard');
        $this->assertContainerBuilderNotHasService('app.landing');
        $this->assertContainerBuilderNotHasService(PostLanding::class);
    }

    /**
     * @test
     */
    public function it_resolves_a_class_given_as_a_parameter_and_accepts_one_layout_registered_twice(): void
    {
        $this->setParameter('app.landing.class', Landing::class);
        $this->setDefinition('app.landing', (new Definition('%app.landing.class%'))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition(Landing::class, (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));
        $this->setParameter('setono_sylius_cms.default_layouts', ['page' => 'landing']);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LayoutRegistry::class, 0, ['page' => ['landing' => Landing::class]]);
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_layouts_of_one_content_type_with_the_same_code(): void
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
    public function it_refuses_a_layout_code_that_is_not_a_valid_code(): void
    {
        $this->setDefinition('app.invalid', (new Definition(InvalidLayoutCode::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The layout code "Invalid-Code" of %s must match /^[a-z][a-z0-9_]*$/', InvalidLayoutCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_layouts_content_type_that_is_not_a_content_type_class(): void
    {
        $this->setDefinition('app.plain', (new Definition(LayoutOfAPlainClass::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The layout %s belongs to stdClass, which is no content type class', LayoutOfAPlainClass::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_layout_of_a_content_type_without_its_own_url(): void
    {
        $this->setDefinition('app.recipe_landing', (new Definition(RecipeLanding::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The layout %s belongs to the content type "recipe", which has no URL of its own. Only content types with their own URL have layouts', RecipeLanding::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_default_layouts_of_one_content_type(): void
    {
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition('app.wide', (new Definition(Wide::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The content type "page" has more than one default layout: %s and %s', Standard::class, Wide::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function a_default_layout_named_in_the_config_settles_two_defaults(): void
    {
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition('app.wide', (new Definition(Wide::class))->addTag('setono_sylius_cms.layout'));
        $this->setParameter('setono_sylius_cms.default_layouts', ['page' => 'wide']);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LayoutRegistry::class, 0, ['page' => ['standard' => Standard::class, 'wide' => Wide::class]]);
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_content_type_without_a_default_layout(): void
    {
        $this->setDefinition('app.landing', (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "page" has no default layout');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_when_the_default_layout_is_disabled(): void
    {
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));
        $this->setDefinition('app.landing', (new Definition(Landing::class))->addTag('setono_sylius_cms.layout'));
        $this->setParameter('setono_sylius_cms.disabled.layouts', ['page.standard']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The layout "standard" is the default layout of the content type "page", so it can\'t be disabled');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_default_layout_named_for_a_content_type_without_layouts(): void
    {
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));
        $this->setParameter('setono_sylius_cms.default_layouts', ['post' => 'landing']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setono_sylius_cms.content_types.post.default_layout names the layout "landing", but the content type "post" has no layouts');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_a_content_type_with_its_own_url_and_no_layouts(): void
    {
        $this->setDefinition(ContentTypeRegistry::class, new Definition(ContentTypeRegistry::class, [[
            'page' => ['model' => Page::class, 'translation' => null, 'resource' => 'setono_sylius_cms.page'],
            'post' => ['model' => Post::class, 'translation' => null, 'resource' => 'app.post'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]]));
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The content type "post" has its own URL, so it needs a default layout. Add a class with #[Layout(contentType: %s::class, default: true)]', Post::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function content_types_with_their_own_url_and_layouts_and_those_without_a_url_pass(): void
    {
        $this->setDefinition(ContentTypeRegistry::class, new Definition(ContentTypeRegistry::class, [[
            'page' => ['model' => Page::class, 'translation' => null, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]]));
        $this->setDefinition('app.standard', (new Definition(Standard::class))->addTag('setono_sylius_cms.layout'));

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LayoutRegistry::class, 0, ['page' => ['standard' => Standard::class]]);
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
