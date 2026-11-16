<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\DependencyInjection\SetonoSyliusCMSExtension;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\LandingFromOne;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Factory\TranslatableFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class SetonoSyliusCMSExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusCMSExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_loads_the_service_definitions_with_an_empty_config(): void
    {
        $this->load();

        $resources = array_map(static fn (ResourceInterface $resource): string => (string) $resource, $this->container->getResources());

        self::assertContains(dirname(__DIR__, 3) . '/src/Resources/config/services.xml', $resources);
    }

    /**
     * @test
     */
    public function it_rejects_an_unknown_option(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "unknown" under "setono_sylius_cms"');

        $this->load(['unknown' => true]);
    }

    /**
     * @test
     */
    public function it_turns_on_the_doctrine_mapping_of_the_model_namespace(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.driver.doctrine/orm', true);
    }

    /**
     * @test
     */
    public function it_autoconfigures_field_type_handlers(): void
    {
        $this->load();

        $instanceof = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(FieldHandlerInterface::class, $instanceof);
        self::assertTrue($instanceof[FieldHandlerInterface::class]->hasTag('setono_sylius_cms.field_handler'));
    }

    /**
     * @test
     */
    public function it_autoconfigures_block_types_by_their_attribute(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();
        self::assertArrayHasKey(BlockType::class, $configurators);

        $definition = new ChildDefinition('');
        $configurators[BlockType::class]($definition, new BlockType('plain', 'app.plain', 'plain.html.twig'), new \ReflectionClass(Plain::class));

        self::assertTrue($definition->hasTag('setono_sylius_cms.block_type'));
    }

    /**
     * @test
     */
    public function it_autoconfigures_layouts_and_upgraders_by_their_attributes(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();

        $layout = new ChildDefinition('');
        $configurators[Layout::class]($layout, new Layout('landing', Page::class, 'app.landing', 'landing.html.twig'), new \ReflectionClass(Landing::class));
        self::assertTrue($layout->hasTag('setono_sylius_cms.layout'));

        $upgrader = new ChildDefinition('');
        $configurators[AsUpgrader::class]($upgrader, new AsUpgrader(Landing::class, 1), new \ReflectionClass(LandingFromOne::class));
        self::assertTrue($upgrader->hasTag('setono_sylius_cms.upgrader'));
    }

    /**
     * @test
     */
    public function it_hands_the_disabled_block_types_to_the_block_type_registry(): void
    {
        $this->load(['disabled' => ['block_types' => ['spacer']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.block_types', ['spacer']);
    }

    /**
     * @test
     */
    public function it_hands_the_block_styles_added_in_config_to_the_block_type_registry(): void
    {
        $blockStyles = ['group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']]];

        $this->load(['block_styles' => $blockStyles]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.block_styles', $blockStyles);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BlockTypeRegistry::class, 3, '%setono_sylius_cms.block_styles%');
    }

    /**
     * @test
     */
    public function it_hands_the_fallback_locales_to_the_fallback_chain(): void
    {
        $this->load(['fallback_locales' => ['de_CH' => ['de_DE']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.fallback_locales', ['de_CH' => ['de_DE']]);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(FallbackChain::class, 1, '%setono_sylius_cms.fallback_locales%');
    }

    /**
     * @test
     */
    public function it_loads_the_stylesheet_through_the_shop_layout_stylesheets_template_event_before_sylius_own_styles(): void
    {
        $this->load();

        self::assertSame([[
            'events' => [
                'sylius.shop.layout.stylesheets' => [
                    'blocks' => [
                        'setono_sylius_cms_stylesheet' => ['template' => '@SetonoSyliusCMSPlugin/shop/stylesheet.html.twig', 'priority' => 20],
                    ],
                ],
            ],
        ]], $this->container->getExtensionConfig('sylius_ui'));
    }

    /**
     * @test
     */
    public function it_does_not_load_the_stylesheet_when_assets_is_off(): void
    {
        $this->container->loadFromExtension('setono_sylius_cms', ['assets' => false]);

        $this->load(['assets' => false]);

        self::assertSame([], $this->container->getExtensionConfig('sylius_ui'));
    }

    /**
     * @test
     */
    public function it_registers_page_as_a_sylius_resource(): void
    {
        $this->load();

        $resources = $this->container->getParameter('sylius.resources');
        self::assertIsArray($resources);
        self::assertSame([
            'driver' => 'doctrine/orm',
            'classes' => [
                'model' => Page::class,
                'interface' => PageInterface::class,
                'controller' => ResourceController::class,
                'factory' => TranslatableFactory::class,
            ],
            'translation' => [
                'classes' => [
                    'model' => PageTranslation::class,
                    'interface' => PageTranslationInterface::class,
                    'controller' => ResourceController::class,
                    'factory' => Factory::class,
                ],
            ],
        ], $resources['setono_sylius_cms.page'] ?? null);

        $this->assertContainerBuilderHasService('setono_sylius_cms.factory.page');
        $this->assertContainerBuilderHasService('setono_sylius_cms.repository.page');
        $this->assertContainerBuilderHasService('setono_sylius_cms.factory.page_translation');
    }

    /**
     * @test
     */
    public function it_registers_the_draft_as_a_sylius_resource(): void
    {
        $this->load();

        $resources = $this->container->getParameter('sylius.resources');
        self::assertIsArray($resources);
        self::assertSame([
            'driver' => 'doctrine/orm',
            'classes' => [
                'model' => Draft::class,
                'interface' => DraftInterface::class,
                'repository' => DraftRepository::class,
                'factory' => Factory::class,
            ],
        ], $resources['setono_sylius_cms.draft'] ?? null);

        $this->assertContainerBuilderHasService('setono_sylius_cms.factory.draft');
        $this->assertContainerBuilderHasService('setono_sylius_cms.repository.draft', DraftRepository::class);
        $this->assertContainerBuilderHasAlias(DraftRepositoryInterface::class, 'setono_sylius_cms.repository.draft');
    }

    /**
     * @test
     */
    public function a_project_overrides_the_page_model_in_the_plugin_config(): void
    {
        $this->load(['resources' => ['page' => ['classes' => ['model' => 'App\\Entity\\Page']]]]);

        /** @var array{'setono_sylius_cms.page': array{classes: array{model: string}}} $resources */
        $resources = $this->container->getParameter('sylius.resources');

        self::assertSame('App\\Entity\\Page', $resources['setono_sylius_cms.page']['classes']['model']);
    }
}
