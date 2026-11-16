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
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepository;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionPruner;
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
    public function it_autoconfigures_problem_kinds(): void
    {
        $this->load();

        $instanceof = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(ProblemKindInterface::class, $instanceof);
        self::assertSame(['setono_sylius_cms.problem_kind' => [[]]], $instanceof[ProblemKindInterface::class]->getTags());
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
    public function it_hands_how_many_revisions_each_entry_keeps_to_the_pruner(): void
    {
        $this->load(['revisions' => ['keep' => 10]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.revisions.keep', 10);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(RevisionPruner::class, 1, '%setono_sylius_cms.revisions.keep%');
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

        self::assertSame([
            'blocks' => [
                'setono_sylius_cms_stylesheet' => ['template' => '@SetonoSyliusCMSPlugin/shop/stylesheet.html.twig', 'priority' => 20],
            ],
        ], $this->templateEvents()['sylius.shop.layout.stylesheets'] ?? null);
    }

    /**
     * @test
     */
    public function it_does_not_load_the_stylesheet_when_assets_is_off(): void
    {
        $this->container->loadFromExtension('setono_sylius_cms', ['assets' => false]);

        $this->load(['assets' => false]);

        self::assertArrayNotHasKey('sylius.shop.layout.stylesheets', $this->templateEvents());
    }

    /**
     * @test
     */
    public function it_builds_the_entry_overview_page_from_template_events_whose_blocks_are_its_header_actions_and_sections(): void
    {
        $this->load();

        $events = $this->templateEvents();
        self::assertSame([
            'blocks' => [
                'open_in_builder' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_open_in_builder.html.twig', 'priority' => 20],
            ],
        ], $events['setono_sylius_cms.admin.entry.show.header_actions'] ?? null);
        self::assertSame([
            'blocks' => [
                'publishing' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_publishing.html.twig', 'priority' => 100],
                'problems' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_problems.html.twig', 'priority' => 90],
                'revisions' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_revisions.html.twig', 'priority' => 60],
                'urls' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_urls.html.twig', 'priority' => 40],
            ],
        ], $events['setono_sylius_cms.admin.entry.show.sections'] ?? null);
    }

    /**
     * @test
     */
    public function it_keeps_the_problem_summary_in_a_cache_pool_on_the_apps_adapter_for_five_minutes(): void
    {
        $this->load();

        self::assertSame(
            [['cache' => ['pools' => ['setono_sylius_cms.cache.problems' => ['adapter' => 'cache.app', 'default_lifetime' => 300]]]]],
            $this->container->getExtensionConfig('framework'),
        );
    }

    /**
     * @test
     */
    public function it_loads_the_stylesheet_of_the_content_badge_on_every_admin_page_after_sylius_own_styles(): void
    {
        $this->load();

        self::assertSame([
            'blocks' => [
                'setono_sylius_cms_menu' => ['template' => '@SetonoSyliusCMSPlugin/admin/menu/stylesheet.html.twig', 'priority' => 5],
            ],
        ], $this->templateEvents()['sylius.admin.layout.stylesheets'] ?? null);
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
    public function it_registers_the_root_of_the_content_types_as_a_sylius_resource_for_the_entry_lists(): void
    {
        $this->load();

        $resources = $this->container->getParameter('sylius.resources');
        self::assertIsArray($resources);
        self::assertSame([
            'driver' => 'doctrine/orm',
            'classes' => [
                'model' => Entry::class,
                'interface' => EntryInterface::class,
                'controller' => ResourceController::class,
                'repository' => EntryRepository::class,
                'factory' => Factory::class,
            ],
        ], $resources['setono_sylius_cms.entry'] ?? null);

        $this->assertContainerBuilderHasService('setono_sylius_cms.controller.entry', ResourceController::class);
        $this->assertContainerBuilderHasService('setono_sylius_cms.repository.entry', EntryRepository::class);
        $this->assertContainerBuilderHasAlias(EntryRepositoryInterface::class, 'setono_sylius_cms.repository.entry');
    }

    /**
     * @test
     */
    public function it_ships_the_entry_lists_as_sylius_grids_of_the_entry_resource(): void
    {
        $this->load();

        /** @var list<array{grids: array<string, array<string, mixed>>, templates: array{action: array<string, string>, filter: array<string, string>}}> $configs */
        $configs = $this->container->getExtensionConfig('sylius_grid');
        self::assertCount(1, $configs);
        [$config] = $configs;

        self::assertSame(['setono_sylius_cms_admin_entry', 'setono_sylius_cms_admin_entry_all'], array_keys($config['grids']));
        $grid = $config['grids']['setono_sylius_cms_admin_entry'];
        self::assertSame([
            'name' => 'doctrine/orm',
            'options' => [
                'class' => '%setono_sylius_cms.model.entry.class%',
                'repository' => ['method' => 'createListQueryBuilder', 'arguments' => ['$contentType']],
            ],
        ], $grid['driver']);
        self::assertSame(['lastEdited' => 'desc'], $grid['sorting']);
        self::assertSame([25, 50, 100], $grid['limits']);
        self::assertSame([
            'title' => self::twigField('setono_sylius_cms.ui.title', 'title', 10),
            'status' => self::twigField('setono_sylius_cms.ui.status', 'status', 30),
            'lastEdited' => ['sortable' => 'lastEditedAt'] + self::twigField('setono_sylius_cms.ui.last_edited', 'last_edited', 50),
            'problems' => self::twigField('setono_sylius_cms.ui.problems', 'problems', 60),
        ], $grid['fields']);
        self::assertSame([
            'search' => [
                'type' => 'string',
                'label' => 'setono_sylius_cms.ui.search',
                'options' => ['fields' => ['translation.title', 'translation.slug']],
                'form_options' => ['type' => 'contains'],
            ],
            'status' => ['type' => 'setono_sylius_cms_entry_status', 'label' => 'setono_sylius_cms.ui.status'],
            'channel' => ['type' => 'setono_sylius_cms_entry_channel', 'label' => 'setono_sylius_cms.ui.channel'],
        ], $grid['filters']);
        self::assertSame([
            'main' => [
                'create' => ['type' => 'setono_sylius_cms_create_entry', 'options' => ['contentType' => '$contentType']],
            ],
            'item' => [
                'delete' => ['type' => 'delete', 'options' => ['link' => ['route' => 'setono_sylius_cms_admin_entry_delete', 'parameters' => ['uuid' => 'resource.uuid']]]],
            ],
        ], $grid['actions']);

        self::assertSame([
            'extends' => 'setono_sylius_cms_admin_entry',
            'sorting' => ['lastEdited' => 'desc'],
            'limits' => [25, 50, 100],
            'fields' => ['type' => self::twigField('setono_sylius_cms.ui.type', 'type', 20)],
        ], $config['grids']['setono_sylius_cms_admin_entry_all']);

        self::assertSame(['setono_sylius_cms_create_entry' => '@SetonoSyliusCMSPlugin/admin/entry/grid/action/create.html.twig'], $config['templates']['action']);

        self::assertSame([
            'setono_sylius_cms_entry_status' => '@SyliusUi/Grid/Filter/select.html.twig',
            'setono_sylius_cms_entry_channel' => '@SyliusUi/Grid/Filter/select.html.twig',
        ], $config['templates']['filter']);
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

    /**
     * The sylius_ui template events the plugin prepends, merged by event name
     *
     * @return array<string, mixed>
     */
    private function templateEvents(): array
    {
        $events = [];
        foreach ($this->container->getExtensionConfig('sylius_ui') as $config) {
            self::assertIsArray($config['events'] ?? null);
            foreach ($config['events'] as $name => $event) {
                self::assertIsString($name);
                self::assertArrayNotHasKey($name, $events, sprintf('The template event %s is prepended once', $name));
                $events[$name] = $event;
            }
        }

        return $events;
    }

    /**
     * @return array{type: string, label: string, path: string, position: int, options: array{template: string}}
     */
    private static function twigField(string $label, string $template, int $position): array
    {
        return [
            'type' => 'twig',
            'label' => $label,
            'path' => '.',
            'position' => $position,
            'options' => ['template' => sprintf('@SetonoSyliusCMSPlugin/admin/entry/grid/field/%s.html.twig', $template)],
        ];
    }
}
