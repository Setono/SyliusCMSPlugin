<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContentTypesPass;
use Setono\SyliusCMSPlugin\Doctrine\DiscriminatorMapListener;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Untagged;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterContentTypesPassTest extends AbstractCompilerPassTestCase
{
    private const PAGE = [
        'classes' => ['model' => Page::class],
        'translation' => ['classes' => ['model' => PageTranslation::class]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(ContentTypeRegistry::class, new Definition(ContentTypeRegistry::class, [[]]));
        $this->setDefinition(DiscriminatorMapListener::class, new Definition(DiscriminatorMapListener::class, [[]]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterContentTypesPass());
    }

    /**
     * @test
     */
    public function it_finds_the_content_types_among_the_sylius_resources(): void
    {
        $this->setParameter('sylius.resources', [
            'sylius.product' => ['classes' => ['model' => \stdClass::class]],
            'setono_sylius_cms.page' => self::PAGE,
            'app.recipe.v2' => ['classes' => ['model' => Recipe::class], 'translation' => ['classes' => ['model' => 'App\Missing']]],
            'app.broken' => ['classes' => []],
        ]);

        $this->compile();

        $contentTypes = [
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe.v2'],
        ];
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, $contentTypes);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DiscriminatorMapListener::class, 0, $contentTypes);

        $locator = $this->container->getDefinition((string) $this->container->getAlias('setono_sylius_cms.entry_factories'));
        self::assertEquals([
            'page' => new ServiceClosureArgument(new Reference('setono_sylius_cms.factory.page')),
            'recipe' => new ServiceClosureArgument(new Reference('app.factory.recipe.v2')),
        ], $locator->getArgument(0));
    }

    /**
     * @test
     */
    public function two_content_types_with_the_same_code_fail_the_build(): void
    {
        $this->setParameter('sylius.resources', ['setono_sylius_cms.page' => self::PAGE, 'app.page' => self::PAGE]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type code "page" is used by both the resources "setono_sylius_cms.page" and "app.page"');

        $this->compile();
    }

    /**
     * @test
     */
    public function an_entry_resource_must_carry_the_attribute(): void
    {
        $this->setParameter('sylius.resources', ['app.untagged' => ['classes' => ['model' => Untagged::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must carry #[ContentType]');

        $this->compile();
    }

    /**
     * @test
     */
    public function a_content_type_code_must_be_a_valid_code(): void
    {
        $this->setParameter('sylius.resources', ['app.invalid' => ['classes' => ['model' => InvalidCode::class]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"Invalid-Code"');

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_sylius_resources(): void
    {
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentTypeRegistry::class, 0, []);
        self::assertFalse($this->container->hasAlias('setono_sylius_cms.entry_factories'));
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(ContentTypeRegistry::class);
        $this->setParameter('sylius.resources', ['setono_sylius_cms.page' => self::PAGE]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DiscriminatorMapListener::class, 0, []);
    }
}
