<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Bestsellers\BestsellersCalculatorInterface;
use Setono\SyliusCMSPlugin\Bestsellers\CompletedOrdersCalculator;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Binding\PropertyPathProperty;
use Setono\SyliusCMSPlugin\Context\ProductContextResolver;
use Setono\SyliusCMSPlugin\Context\TaxonContextResolver;
use Setono\SyliusCMSPlugin\Controller\Admin\BuilderAction;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContentTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\SetonoSyliusCMSExtension;
use Setono\SyliusCMSPlugin\Factory\PlacementFactory;
use Setono\SyliusCMSPlugin\Factory\PlacementFactoryInterface;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFile;
use Setono\SyliusCMSPlugin\Media\Liip\LiipImageProvider;
use Setono\SyliusCMSPlugin\Media\Liip\LocalVariants;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\CodePresetProvider;
use Setono\SyliusCMSPlugin\Problem\PresetProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Profiler\CMSDataCollector;
use Setono\SyliusCMSPlugin\Profiler\TraceableSlotRenderer;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDue;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Setono\SyliusCMSPlugin\Rendering\SlotRendererInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepository;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepository;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionPruner;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\SignedIn;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\StoreLocationResolver;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\LandingFromOne;
use Setono\SyliusCMSPlugin\Transfer\Import\AbandonImport;
use Setono\SyliusCMSPlugin\Transfer\Import\RunImport;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Factory\TranslatableFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

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
     * The profiler panel and what records it, which decorate the rendering services. See "Profiler panel" in
     * docs/spec/rendering.md
     *
     * @test
     */
    public function it_registers_the_profiler_panel_in_debug(): void
    {
        $this->setParameter('kernel.debug', true);

        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(CMSDataCollector::class, 'data_collector', [
            'id' => CMSDataCollector::NAME,
            'template' => '@SetonoSyliusCMSPlugin/profiler/cms.html.twig',
            'priority' => -10,
        ]);
        self::assertSame(SlotRendererInterface::class, $this->container->getDefinition(TraceableSlotRenderer::class)->getDecoratedService()[0] ?? null);
    }

    /**
     * @test
     */
    public function it_registers_no_profiler_panel_without_debug(): void
    {
        $this->setParameter('kernel.debug', false);

        $this->load();

        $this->assertContainerBuilderNotHasService(CMSDataCollector::class);
        $this->assertContainerBuilderNotHasService(TraceableSlotRenderer::class);
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
    public function it_autoconfigures_preset_problem_kinds(): void
    {
        $this->load();

        $instanceof = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(PresetProblemKindInterface::class, $instanceof);
        self::assertSame(['setono_sylius_cms.preset_problem_kind' => [[]]], $instanceof[PresetProblemKindInterface::class]->getTags());
    }

    /**
     * @test
     */
    public function it_autoconfigures_choices_providers(): void
    {
        $this->load();

        $instanceof = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(ChoicesProviderInterface::class, $instanceof);
        self::assertSame(['setono_sylius_cms.choices_provider' => [[]]], $instanceof[ChoicesProviderInterface::class]->getTags());
    }

    /**
     * @test
     */
    public function it_autoconfigures_condition_kinds_by_their_attribute(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();
        self::assertArrayHasKey(AsConditionKind::class, $configurators);

        $definition = new ChildDefinition('');
        $configurators[AsConditionKind::class]($definition, new AsConditionKind('signed_in', 'app.signed_in'), new \ReflectionClass(SignedIn::class));

        self::assertSame(['setono_sylius_cms.condition_kind' => [[]]], $definition->getTags());
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
    public function it_autoconfigures_sources_by_their_attribute(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();
        self::assertArrayHasKey(AsSource::class, $configurators);

        $definition = new ChildDefinition('');
        $configurators[AsSource::class]($definition, new AsSource('on_sale', 'app.on_sale', ProductInterface::class), new \ReflectionClass(OnSale::class));

        self::assertSame(['setono_sylius_cms.source' => [[]]], $definition->getTags());
    }

    /**
     * @test
     */
    public function it_autoconfigures_context_resolvers_by_their_attribute(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();
        self::assertArrayHasKey(AsContextResolver::class, $configurators);

        $definition = new ChildDefinition('');
        $configurators[AsContextResolver::class]($definition, new AsContextResolver('store_location'), new \ReflectionClass(StoreLocationResolver::class));

        self::assertSame(['setono_sylius_cms.context_resolver' => [[]]], $definition->getTags());
    }

    /**
     * @test
     */
    public function it_registers_the_product_and_taxon_resolvers_with_a_low_priority_so_a_project_s_replace_them(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(ProductContextResolver::class, 'setono_sylius_cms.context_resolver', ['priority' => -100]);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(TaxonContextResolver::class, 'setono_sylius_cms.context_resolver', ['priority' => -100]);
    }

    /**
     * @test
     */
    public function it_hands_the_disabled_content_types_to_the_pass_that_registers_the_content_types(): void
    {
        $this->load(['disabled' => ['content_types' => ['blog_post']]]);

        $this->assertContainerBuilderHasParameter(RegisterContentTypesPass::DISABLED_PARAMETER, ['blog_post']);
    }

    /**
     * @test
     */
    public function it_hands_the_disabled_sources_to_the_source_registry(): void
    {
        $this->load(['disabled' => ['sources' => ['newest_products']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.sources', ['newest_products']);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(SourceRegistry::class, 3, '%setono_sylius_cms.disabled.sources%');
    }

    /**
     * @test
     */
    public function it_prepends_the_stock_slots_to_the_slots_config(): void
    {
        $this->load();

        /** @var array<string, array{label: string, provides: list<string>, template_event: array{name: string, priority: int}}>|null $slots */
        $slots = $this->container->getExtensionConfig('setono_sylius_cms')[0]['slots'] ?? null;
        self::assertIsArray($slots);
        self::assertSame([
            'shop.top' => ['sylius.shop.layout.header', 30, []],
            'shop.before_content' => ['sylius.shop.layout.before_content', 0, []],
            'shop.after_content' => ['sylius.shop.layout.after_content', 0, []],
            'shop.footer' => ['sylius.shop.layout.footer', -10, []],
            'store_homepage' => ['sylius.shop.homepage', 100, []],
            'product.top' => ['sylius.shop.layout.before_content', -10, ['product']],
            'product.sidebar' => ['sylius.shop.product.show.right_sidebar', 1, ['product']],
            'product.after_price' => ['sylius.shop.product.show.right_sidebar', 28, ['product']],
            'product.before_tabs' => ['sylius.shop.product.show.content', 22, ['product']],
            'product.details_tab' => ['sylius.shop.product.show.tab_details', 5, ['product']],
            'product.after_associations' => ['sylius.shop.product.show.content', 5, ['product']],
            'taxon.header' => ['sylius.shop.taxon.header', 1, ['taxon']],
            'taxon.before_grid' => ['sylius.shop.product.index.before_list', 0, ['taxon']],
            'taxon.after_grid' => ['sylius.shop.product.index.before_pagination', -10, ['taxon']],
            'taxon.sidebar' => ['sylius.shop.product.index.after_vertical_menu', 0, ['taxon']],
            'cart.after_summary' => ['sylius.shop.cart.suggestions', 100, []],
            'checkout.sidebar' => ['sylius.shop.checkout.sidebar', 0, []],
            'order.thank_you' => ['sylius.shop.order.thank_you.after_message', 0, []],
            'account.dashboard' => ['sylius.shop.account.dashboard.after_information', 0, []],
        ], array_map(static fn (array $slot): array => [$slot['template_event']['name'], $slot['template_event']['priority'], $slot['provides']], $slots));

        foreach ($slots as $identifier => $slot) {
            self::assertSame('setono_sylius_cms.slot.' . $identifier, $slot['label']);
            self::assertArrayNotHasKey('cap', $slot, 'No stock slot is capped');
        }
    }

    /**
     * @test
     */
    public function it_hands_the_slots_to_the_slot_registry(): void
    {
        $slots = ['app.store_locator.top' => ['label' => 'app.cms.slot.store_locator_top', 'provides' => ['store_location'], 'cap' => 1]];

        $this->load(['slots' => $slots, 'context_keys' => ['store_location' => ['class' => \stdClass::class, 'label' => 'app.cms.context_key.store_location']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.slots', $slots);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(SlotRegistry::class, 0, '%setono_sylius_cms.slots%');
        $this->assertContainerBuilderHasAlias(SlotRegistryInterface::class, SlotRegistry::class);
    }

    /**
     * @test
     */
    public function it_prepends_the_product_taxon_and_entry_context_keys_to_the_context_keys_config(): void
    {
        $this->load();

        self::assertSame([
            'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
            'taxon' => ['class' => TaxonInterface::class, 'label' => 'setono_sylius_cms.context_key.taxon'],
            'entry' => ['class' => EntryInterface::class, 'label' => 'setono_sylius_cms.context_key.entry'],
        ], $this->container->getExtensionConfig('setono_sylius_cms')[0]['context_keys'] ?? null);
    }

    /**
     * @test
     */
    public function it_autoconfigures_bindable_properties_by_their_attribute(): void
    {
        $this->load();

        $configurators = $this->container->getAutoconfiguredAttributes();
        self::assertArrayHasKey(AsBindableProperty::class, $configurators);

        $definition = new ChildDefinition('');
        $configurators[AsBindableProperty::class]($definition, new AsBindableProperty('product', 'brand', 'app.binding.brand', BindingType::Text), new \ReflectionClass(ProductBrand::class));

        self::assertSame(['setono_sylius_cms.bindable_property' => [[]]], $definition->getTags());
    }

    /**
     * @test
     */
    public function it_registers_a_bindable_property_per_property_path_the_config_declares_with_its_declaration_in_its_tag(): void
    {
        $this->load(['bindable_properties' => ['product' => ['brand' => ['label' => 'app.cms.binding.brand', 'type' => 'text', 'path' => 'brand.name']]]]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('setono_sylius_cms.bindable_property.product.brand', 0, 'brand.name');
        $this->assertContainerBuilderHasServiceDefinitionWithTag('setono_sylius_cms.bindable_property.product.brand', 'setono_sylius_cms.bindable_property', [
            'context_key' => 'product',
            'code' => 'brand',
            'label' => 'app.cms.binding.brand',
            'type' => 'text',
        ]);
        self::assertSame(PropertyPathProperty::class, $this->container->getDefinition('setono_sylius_cms.bindable_property.product.brand')->getClass());
    }

    /**
     * @test
     */
    public function it_hands_the_context_keys_to_the_context_key_registry(): void
    {
        $contextKeys = ['store_location' => ['class' => \stdClass::class, 'label' => 'app.cms.context_key.store_location']];

        $this->load(['context_keys' => $contextKeys]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.context_keys', $contextKeys);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContextKeyRegistry::class, 0, '%setono_sylius_cms.context_keys%');
        $this->assertContainerBuilderHasAlias(ContextKeyRegistryInterface::class, ContextKeyRegistry::class);
    }

    /**
     * @test
     */
    public function it_refuses_a_slot_that_provides_a_context_key_the_code_does_not_declare(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The slot "app.store_locator.top" provides the context key "store_location", which isn\'t declared under setono_sylius_cms.context_keys');

        $this->load(['slots' => ['app.store_locator.top' => ['label' => 'app.cms.slot.store_locator_top', 'provides' => ['store_location']]]]);
    }

    /**
     * @test
     */
    public function it_adds_one_block_that_renders_the_slot_to_the_template_event_of_each_slot_tied_to_one_at_its_priority(): void
    {
        $this->container->loadFromExtension('setono_sylius_cms', ['slots' => [
            'app.store_locator.top' => ['label' => 'app.cms.slot.store_locator_top', 'template_event' => ['name' => 'app.store_locator.top', 'priority' => 5]],
            'page.landing.after_hero' => ['label' => 'app.cms.slot.after_hero'],
            'shop.footer' => ['template_event' => ['priority' => -20]],
        ]]);

        $this->load();

        $events = $this->templateEvents();
        self::assertSame(['blocks' => [
            'setono_sylius_cms.slot.shop.before_content' => self::slotBlock('shop.before_content', 0),
            'setono_sylius_cms.slot.product.top' => self::slotBlock('product.top', -10),
        ]], $events['sylius.shop.layout.before_content'] ?? null);
        self::assertSame(['blocks' => [
            'setono_sylius_cms.slot.product.sidebar' => self::slotBlock('product.sidebar', 1),
            'setono_sylius_cms.slot.product.after_price' => self::slotBlock('product.after_price', 28),
        ]], $events['sylius.shop.product.show.right_sidebar'] ?? null);
        self::assertSame(['blocks' => ['setono_sylius_cms.slot.shop.footer' => self::slotBlock('shop.footer', -20)]], $events['sylius.shop.layout.footer'] ?? null);
        self::assertSame(['blocks' => ['setono_sylius_cms.slot.app.store_locator.top' => self::slotBlock('app.store_locator.top', 5)]], $events['app.store_locator.top'] ?? null);

        // The 19 stock slots and the project's slot tied to a template event, but not the one rendered with cms_slot()
        $blocks = [];
        foreach ($events as $event) {
            self::assertIsArray($event);
            self::assertIsArray($event['blocks'] ?? null);
            $blocks = [...$blocks, ...array_filter(array_keys($event['blocks']), static fn (int|string $name): bool => str_starts_with((string) $name, 'setono_sylius_cms.slot.'))];
        }
        self::assertCount(20, $blocks);
        self::assertNotContains('setono_sylius_cms.slot.page.landing.after_hero', $blocks);
    }

    /**
     * @test
     */
    public function it_hands_the_disabled_block_types_to_the_block_type_registry(): void
    {
        $this->load(['disabled' => ['block_types' => ['spacer']], 'embed_code' => true]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.block_types', ['spacer']);
    }

    /**
     * @test
     */
    public function it_disables_the_embed_code_block_type_until_a_project_opts_in(): void
    {
        $this->load(['disabled' => ['block_types' => ['spacer']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.block_types', ['spacer', 'embed_code']);
        $this->assertContainerBuilderHasParameter('setono_sylius_cms.embed_code', false);
    }

    /**
     * @test
     */
    public function it_disables_the_embed_code_block_type_once_when_a_project_also_lists_it_as_disabled(): void
    {
        $this->load(['disabled' => ['block_types' => ['embed_code']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.block_types', ['embed_code']);
    }

    /**
     * @test
     */
    public function it_enables_the_embed_code_block_type_when_a_project_opts_in(): void
    {
        $this->load(['embed_code' => true]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.block_types', []);
        $this->assertContainerBuilderHasParameter('setono_sylius_cms.embed_code', true);
    }

    /**
     * @test
     */
    public function it_hands_the_disabled_layouts_and_the_configured_default_layouts_to_the_layouts_pass(): void
    {
        $this->load([
            'disabled' => ['layouts' => ['page.landing']],
            'content_types' => ['page' => ['default_layout' => 'wide'], 'recipe' => []],
        ]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.layouts', ['page.landing']);
        $this->assertContainerBuilderHasParameter('setono_sylius_cms.default_layouts', ['page' => 'wide']);
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
    public function it_stores_media_in_the_plugin_s_own_local_storage_by_default_which_flysystem_bundle_defines_as_sylius_defines_its_own(): void
    {
        $this->load();

        self::assertSame([['storages' => [
            'setono_sylius_cms.storage' => [
                'adapter' => 'local',
                'options' => ['directory' => '%kernel.project_dir%/public/media/cms'],
                'visibility' => 'public',
                'directory_visibility' => 'public',
            ],
        ]]], $this->container->getExtensionConfig('flysystem'));
        self::assertFalse($this->container->has('setono_sylius_cms.storage'), 'The plugin defines no storage of its own');
        self::assertFalse($this->container->hasDefinition('setono_sylius_cms.media.storage') || $this->container->hasAlias('setono_sylius_cms.media.storage'), 'The storage goes by one name');
    }

    /**
     * @test
     */
    public function it_stores_media_in_the_storage_a_project_configures_under_the_one_name(): void
    {
        $this->container->loadFromExtension('setono_sylius_cms', ['media' => ['storage' => 'app.s3_storage']]);

        $this->load(['media' => ['storage' => 'app.s3_storage']]);

        $this->assertContainerBuilderHasAlias('setono_sylius_cms.storage', 'app.s3_storage');
        self::assertSame([], $this->container->getExtensionConfig('flysystem'), 'No local storage is defined');
    }

    /**
     * @test
     */
    public function it_looks_for_made_variants_in_the_default_local_storage_s_directory(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LocalVariants::class, 0, '%kernel.project_dir%/public/media/cms');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(LiipImageProvider::class, 2, new Reference(LocalVariants::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }

    /**
     * @test
     *
     * @dataProvider elsewhere
     *
     * @param array<string, mixed> $media
     */
    public function it_does_not_look_for_made_variants_when_media_is_stored_or_variants_are_cached_elsewhere(array $media): void
    {
        $this->load(['media' => $media]);

        $this->assertContainerBuilderNotHasService(LocalVariants::class);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function elsewhere(): iterable
    {
        yield 'another storage' => [['storage' => 'app.s3_storage']];
        yield 'another Liip resolver' => [['liip' => ['resolver' => 'app_s3_cache']]];
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
    public function it_hands_what_the_project_changes_about_content_types_to_the_pass_that_registers_them(): void
    {
        $this->load(['content_types' => ['page' => ['prefix' => 'pg']]]);

        $this->assertContainerBuilderHasParameter(RegisterContentTypesPass::CONFIG_PARAMETER, ['page' => ['prefix' => 'pg']]);
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
    public function it_loads_the_carousel_script_through_the_shop_layout_javascripts_template_event_after_sylius_own_scripts(): void
    {
        $this->load();

        self::assertSame([
            'blocks' => [
                'setono_sylius_cms_javascript' => ['template' => '@SetonoSyliusCMSPlugin/shop/javascript.html.twig', 'priority' => 5],
            ],
        ], $this->templateEvents()['sylius.shop.layout.javascripts'] ?? null);
    }

    /**
     * @test
     */
    public function it_does_not_load_the_carousel_script_when_assets_is_off(): void
    {
        $this->container->loadFromExtension('setono_sylius_cms', ['assets' => false]);

        $this->load(['assets' => false]);

        self::assertArrayNotHasKey('sylius.shop.layout.javascripts', $this->templateEvents());
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
                'share_draft' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_share_draft.html.twig', 'priority' => 10],
            ],
        ], $events['setono_sylius_cms.admin.entry.show.header_actions'] ?? null);
        self::assertSame([
            'blocks' => [
                'publishing' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_publishing.html.twig', 'priority' => 100],
                'problems' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_problems.html.twig', 'priority' => 90],
                'placements' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_placements.html.twig', 'priority' => 80],
                'where_it_shows' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_where_it_shows.html.twig', 'priority' => 70],
                'revisions' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_revisions.html.twig', 'priority' => 60],
                'share_links' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_share_links.html.twig', 'priority' => 50],
                'urls' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_urls.html.twig', 'priority' => 40],
                'used_in' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_used_in.html.twig', 'priority' => 30],
                'translations' => ['template' => '@SetonoSyliusCMSPlugin/admin/entry/show/_translations.html.twig', 'priority' => 20],
            ],
        ], $events['setono_sylius_cms.admin.entry.show.sections'] ?? null);
    }

    /**
     * @test
     */
    public function it_builds_the_overview_from_a_template_event_whose_blocks_are_its_panels_in_the_specs_order(): void
    {
        $this->load();

        self::assertSame([
            'blocks' => [
                'needs_attention' => ['template' => '@SetonoSyliusCMSPlugin/admin/overview/_needs_attention.html.twig', 'priority' => 40],
                'coming_up' => ['template' => '@SetonoSyliusCMSPlugin/admin/overview/_coming_up.html.twig', 'priority' => 30],
                'translations' => ['template' => '@SetonoSyliusCMSPlugin/admin/overview/_translations.html.twig', 'priority' => 20],
                'drafts_in_progress' => ['template' => '@SetonoSyliusCMSPlugin/admin/overview/_drafts_in_progress.html.twig', 'priority' => 10],
            ],
        ], $this->templateEvents()['setono_sylius_cms.admin.overview.panels'] ?? null);
    }

    /**
     * @test
     */
    public function it_routes_the_messages_that_run_a_scheduled_publish_delete_a_replaced_file_and_run_or_abandon_an_admin_import_to_sylius_main_transport(): void
    {
        $this->load();

        $framework = $this->container->getExtensionConfig('framework');
        self::assertSame([['messenger' => ['routing' => [
            ScheduledPublishDue::class => 'main',
            DeleteReplacedFile::class => 'main',
            RunImport::class => 'main',
            AbandonImport::class => 'main',
        ]]]], $framework);
    }

    /**
     * @test
     */
    public function it_makes_the_grace_period_a_parameter(): void
    {
        $this->load(['media' => ['grace_period' => 'PT6H']]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.media.grace_period', 'PT6H');
    }

    /**
     * @test
     */
    public function it_hands_the_field_control_scripts_to_the_builder_page(): void
    {
        $this->load(['builder' => ['scripts' => ['/build/app/cms/controls.js']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.builder.scripts', ['/build/app/cms/controls.js']);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(BuilderAction::class, 14, '%setono_sylius_cms.builder.scripts%');
    }

    /**
     * See "Code presets" in docs/spec/layouts-and-presets.md
     *
     * @test
     */
    public function it_hands_the_code_preset_files_it_finds_in_the_registered_directories_by_code_and_the_disabled_codes_to_the_code_preset_provider(): void
    {
        $directory = (string) realpath(dirname(__DIR__) . '/Preset/Fixture/presets');
        $this->setParameter('kernel.project_dir', dirname($directory));

        $this->load(['presets' => ['paths' => ['%kernel.project_dir%/presets']], 'disabled' => ['presets' => ['faq']]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.presets.files', ['faq' => $directory . '/faq.json', 'hero' => $directory . '/hero.json']);
        $this->assertContainerBuilderHasParameter('setono_sylius_cms.disabled.presets', ['faq']);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(CodePresetProvider::class, 0, '%setono_sylius_cms.presets.files%');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(CodePresetProvider::class, 1, '%setono_sylius_cms.disabled.presets%');
        $this->assertContainerBuilderHasServiceDefinitionWithTag(CodePresetProvider::class, 'setono_sylius_cms.preset_provider', ['priority' => 100]);
        // A file added to a directory rebuilds the container
        self::assertContains((string) new DirectoryResource($directory, '/\.json$/'), array_map(static fn (ResourceInterface $resource): string => (string) $resource, $this->container->getResources()));
    }

    /**
     * See "Shipped code presets" in docs/spec/layouts-and-presets.md
     *
     * @test
     */
    public function it_prepends_its_own_presets_directory_to_the_presets_paths_as_a_bundle_does(): void
    {
        $this->load();

        $presets = $this->container->getExtensionConfig('setono_sylius_cms')[0]['presets'] ?? null;
        self::assertIsArray($presets);
        self::assertIsArray($presets['paths'] ?? null);
        self::assertCount(1, $presets['paths']);
        self::assertIsString($presets['paths'][0] ?? null);
        self::assertSame(dirname(__DIR__, 3) . '/src/Resources/presets', realpath($presets['paths'][0]));
    }

    /**
     * @test
     */
    public function it_hands_the_bestsellers_window_to_the_default_calculator_which_the_calculator_interface_aliases(): void
    {
        $this->load(['bestsellers' => ['window' => 'P7D']]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.bestsellers.window', 'P7D');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(CompletedOrdersCalculator::class, 3, '%setono_sylius_cms.bestsellers.window%');
        $this->assertContainerBuilderHasAlias(BestsellersCalculatorInterface::class, CompletedOrdersCalculator::class);
    }

    /**
     * @test
     */
    public function it_makes_the_widths_of_the_image_variants_a_parameter(): void
    {
        $this->load(['media' => ['widths' => [400, 800]]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.media.widths', [400, 800]);
    }

    /**
     * @test
     */
    public function it_adds_a_liip_filter_set_per_format_that_reads_and_caches_with_the_plugin_s_own_loader_and_resolver(): void
    {
        $this->load();

        self::assertSame([['filter_sets' => [
            'setono_sylius_cms_webp' => ['data_loader' => 'setono_sylius_cms', 'cache' => 'setono_sylius_cms', 'quality' => 80, 'format' => 'webp', 'filters' => [], 'post_processors' => []],
            'setono_sylius_cms_avif' => ['data_loader' => 'setono_sylius_cms', 'cache' => 'setono_sylius_cms', 'quality' => 60, 'format' => 'avif', 'filters' => [], 'post_processors' => []],
            'setono_sylius_cms_original' => ['data_loader' => 'setono_sylius_cms', 'cache' => 'setono_sylius_cms', 'quality' => 85, 'format' => null, 'filters' => [], 'post_processors' => []],
        ]]], $this->container->getExtensionConfig('liip_imagine'));
    }

    /**
     * @test
     */
    public function it_makes_the_filter_sets_read_and_cache_with_the_liip_loader_and_resolver_a_project_names(): void
    {
        $this->container->loadFromExtension('setono_sylius_cms', ['media' => ['liip' => ['loader' => 'app_s3', 'resolver' => 'app_s3_cache']]]);

        $this->load(['media' => ['liip' => ['loader' => 'app_s3', 'resolver' => 'app_s3_cache']]]);

        /** @var array<string, array{data_loader: string, cache: string}> $filterSets */
        $filterSets = $this->container->getExtensionConfig('liip_imagine')[0]['filter_sets'] ?? [];
        self::assertCount(3, $filterSets);
        foreach ($filterSets as $filterSet) {
            self::assertSame('app_s3', $filterSet['data_loader']);
            self::assertSame('app_s3_cache', $filterSet['cache']);
        }
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
    public function it_registers_snippet_as_a_sylius_resource_whose_translations_are_entry_translations(): void
    {
        $this->load();

        $resources = $this->container->getParameter('sylius.resources');
        self::assertIsArray($resources);
        self::assertSame([
            'driver' => 'doctrine/orm',
            'classes' => [
                'model' => Snippet::class,
                'interface' => SnippetInterface::class,
                'controller' => ResourceController::class,
                'factory' => TranslatableFactory::class,
            ],
            'translation' => [
                'classes' => [
                    'model' => EntryTranslation::class,
                    'interface' => EntryTranslationInterface::class,
                    'controller' => ResourceController::class,
                    'factory' => Factory::class,
                ],
            ],
        ], $resources['setono_sylius_cms.snippet'] ?? null);

        $this->assertContainerBuilderHasService('setono_sylius_cms.factory.snippet');
        $this->assertContainerBuilderHasService('setono_sylius_cms.repository.snippet');
    }

    /**
     * @test
     */
    public function it_registers_the_placement_as_a_sylius_resource_whose_factory_creates_placements_of_the_configured_model(): void
    {
        $this->load();

        $resources = $this->container->getParameter('sylius.resources');
        self::assertIsArray($resources);
        self::assertSame([
            'driver' => 'doctrine/orm',
            'classes' => [
                'model' => Placement::class,
                'interface' => PlacementInterface::class,
                'repository' => PlacementRepository::class,
                'factory' => PlacementFactory::class,
            ],
        ], $resources['setono_sylius_cms.placement'] ?? null);

        $this->assertContainerBuilderHasService('setono_sylius_cms.repository.placement', PlacementRepository::class);
        $this->assertContainerBuilderHasAlias(PlacementRepositoryInterface::class, 'setono_sylius_cms.repository.placement');
        $this->assertContainerBuilderHasService('setono_sylius_cms.factory.placement', PlacementFactory::class);
        $this->assertContainerBuilderHasAlias(PlacementFactoryInterface::class, 'setono_sylius_cms.factory.placement');
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

        self::assertSame(['setono_sylius_cms_admin_entry', 'setono_sylius_cms_admin_entry_all', 'setono_sylius_cms_admin_entry_collection'], array_keys($config['grids']));
        $grid = $config['grids']['setono_sylius_cms_admin_entry'];
        self::assertSame([
            'name' => 'doctrine/orm',
            'options' => [
                'class' => '%setono_sylius_cms.model.entry.class%',
                'repository' => ['method' => 'createListQueryBuilder', 'arguments' => ['$contentType', '%setono_sylius_cms.disabled.content_types%']],
            ],
        ], $grid['driver']);
        self::assertSame(['lastEdited' => 'desc'], $grid['sorting']);
        self::assertSame([25, 50, 100], $grid['limits']);
        self::assertSame([
            'title' => self::twigField('setono_sylius_cms.ui.title', 'title', 10),
            'status' => self::twigField('setono_sylius_cms.ui.status', 'status', 30),
            'translations' => self::twigField('setono_sylius_cms.ui.translations', 'translations', 40),
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
            'translation' => ['type' => 'setono_sylius_cms_entry_translation', 'label' => 'setono_sylius_cms.ui.translations'],
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

        // A collection with categories lists both, by type, and its create dialog asks which of the two
        self::assertSame([
            'extends' => 'setono_sylius_cms_admin_entry',
            'driver' => ['options' => ['repository' => ['method' => 'createCollectionListQueryBuilder', 'arguments' => ['$contentType', '%setono_sylius_cms.disabled.content_types%']]]],
            'sorting' => ['lastEdited' => 'desc'],
            'limits' => [25, 50, 100],
            'fields' => ['type' => self::twigField('setono_sylius_cms.ui.type', 'type', 20)],
            'actions' => ['main' => ['create' => ['type' => 'setono_sylius_cms_create_entry', 'options' => ['contentType' => null, 'collection' => '$contentType']]]],
        ], $config['grids']['setono_sylius_cms_admin_entry_collection']);

        self::assertSame(['setono_sylius_cms_create_entry' => '@SetonoSyliusCMSPlugin/admin/entry/grid/action/create.html.twig'], $config['templates']['action']);

        self::assertSame([
            'setono_sylius_cms_entry_status' => '@SyliusUi/Grid/Filter/select.html.twig',
            'setono_sylius_cms_entry_channel' => '@SyliusUi/Grid/Filter/select.html.twig',
            'setono_sylius_cms_entry_translation' => '@SyliusUi/Grid/Filter/select.html.twig',
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
     * @return array{template: string, priority: int, context: array{_cms_slot: string}}
     */
    private static function slotBlock(string $slot, int $priority): array
    {
        return ['template' => '@SetonoSyliusCMSPlugin/slot/template_event.html.twig', 'priority' => $priority, 'context' => ['_cms_slot' => $slot]];
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
