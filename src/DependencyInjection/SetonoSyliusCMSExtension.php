<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Admin\Grid\ChannelFilter;
use Setono\SyliusCMSPlugin\Admin\Grid\StatusFilter;
use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterConditionKindsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterContentTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLayoutsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLinkTargetsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterProblemKindsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterSourcesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterUpgradersPass;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFile;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDue;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusCMSExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public const FIELD_HANDLER_TAG = 'setono_sylius_cms.field_handler';

    public const CHOICES_PROVIDER_TAG = 'setono_sylius_cms.choices_provider';

    /** The entry lists show the entries edited last first */
    private const GRID_SORTING = ['lastEdited' => 'desc'];

    private const GRID_LIMITS = [25, 50, 100];

    /** The grid action type of the entry lists' Create, which opens the create dialog */
    private const CREATE_ENTRY_ACTION = 'setono_sylius_cms_create_entry';

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{disabled: array{block_types: list<string>, layouts: list<string>, sources: list<string>}, block_styles: array<string, array<string, array{label: string, class: string}>>, content_types: array<string, array{prefix?: string, default_layout?: string}>, fallback_locales: array<string, list<string>>, revisions: array{keep: int}, media: array{storage: string, types: array<string, list<string>>, max_upload_size: int|null, grace_period: string}, resources: array<string, array<string, mixed>>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_cms.disabled.block_types', $config['disabled']['block_types']);
        // RegisterLayoutsPass checks them against the layouts the code has
        $container->setParameter('setono_sylius_cms.disabled.layouts', $config['disabled']['layouts']);
        $container->setParameter('setono_sylius_cms.disabled.sources', $config['disabled']['sources']);
        $container->setParameter('setono_sylius_cms.default_layouts', array_filter(array_map(
            static fn (array $contentType): ?string => $contentType['default_layout'] ?? null,
            $config['content_types'],
        ), static fn (?string $layout): bool => null !== $layout));
        $container->setParameter('setono_sylius_cms.block_styles', $config['block_styles']);
        $container->setParameter(RegisterContentTypesPass::CONFIG_PARAMETER, $config['content_types']);
        $container->setParameter('setono_sylius_cms.fallback_locales', $config['fallback_locales']);
        $container->setParameter('setono_sylius_cms.revisions.keep', $config['revisions']['keep']);
        $container->setParameter('setono_sylius_cms.media.types', $config['media']['types']);
        $container->setParameter('setono_sylius_cms.media.max_upload_size', $config['media']['max_upload_size']);
        $container->setParameter('setono_sylius_cms.media.grace_period', $config['media']['grace_period']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // The Flysystem storage media items are stored in: the plugin's own local one unless a project configures another
        $container->setAlias('setono_sylius_cms.media.storage', $config['media']['storage']);

        // Registers the shipped resources, and turns on the Doctrine mapping of the Model namespace
        $this->registerResources('setono_sylius_cms', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

        $container->registerForAutoconfiguration(FieldHandlerInterface::class)->addTag(self::FIELD_HANDLER_TAG);
        $container->registerForAutoconfiguration(ProblemKindInterface::class)->addTag(RegisterProblemKindsPass::TAG);
        $container->registerForAutoconfiguration(ChoicesProviderInterface::class)->addTag(self::CHOICES_PROVIDER_TAG);
        $container->registerAttributeForAutoconfiguration(AsConditionKind::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterConditionKindsPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(BlockType::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterBlockTypesPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(Layout::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterLayoutsPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(AsUpgrader::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterUpgradersPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(AsLinkTarget::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterLinkTargetsPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(AsSource::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterSourcesPass::TAG);
        });
    }

    /**
     * Loads the shipped stylesheet on every shop page, at priority 20: above Sylius's own styles (10),
     * so it declares the cms cascade layer before the theme's CSS. See "Loading the stylesheet and script" in docs/spec/blocks.md
     */
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependGrids($container);
        $this->prependEntryOverviewEvents($container);
        $this->prependProblems($container);
        $this->prependMessenger($container);

        /** @var array{assets: bool} $config */
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));

        if (!$config['assets']) {
            return;
        }

        $container->prependExtensionConfig('sylius_ui', [
            'events' => [
                'sylius.shop.layout.stylesheets' => [
                    'blocks' => [
                        'setono_sylius_cms_stylesheet' => ['template' => '@SetonoSyliusCMSPlugin/shop/stylesheet.html.twig', 'priority' => 20],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Routes the delayed message that runs a scheduled publish to Sylius's main transport, the way Sylius routes the
     * messages that schedule catalog promotions, so the shop's Messenger worker runs it. Projects route it elsewhere in
     * their own framework config. See "Scheduled publishes" in docs/spec/publishing.md
     */
    private function prependMessenger(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'routing' => [
                    ScheduledPublishDue::class => 'main',
                    DeleteReplacedFile::class => 'main',
                ],
            ],
        ]);
    }

    /**
     * The stylesheet of the Content badge on every admin page, after Sylius's own styles (10). Admin\MainMenuListener
     * puts the count on the Content item's link, because Sylius's menu template has no place for a badge of its own,
     * and the stylesheet shows it. Projects change it in their own sylius_ui config. See "Navigation" and "Problems" in
     * docs/spec/admin.md
     */
    private function prependProblems(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_ui', [
            'events' => [
                'sylius.admin.layout.stylesheets' => [
                    'blocks' => [
                        'setono_sylius_cms_menu' => ['template' => '@SetonoSyliusCMSPlugin/admin/menu/stylesheet.html.twig', 'priority' => 5],
                    ],
                ],
            ],
        ]);
    }

    /**
     * The entry overview page is built from two Sylius template events, whose blocks get the entry (`entry`) and its
     * row (`row`, an Admin\EntryRow). A block that has nothing to show for an entry renders nothing:
     *
     * - setono_sylius_cms.admin.entry.show.header_actions: the header's buttons, Open in builder (20) first, then
     *   Share draft (10)
     * - setono_sylius_cms.admin.entry.show.sections: the sections, in the spec's order, which the priorities leave
     *   room in: publishing (100), problems (90), placements (80), where_it_shows (70), revisions (60),
     *   share_links (50), urls (40), used_in (30) and translations (20)
     *
     * A section is a block with its template, and projects add, reorder or switch off blocks in their own sylius_ui
     * config. See "Entry overview page" in docs/spec/admin.md
     */
    private function prependEntryOverviewEvents(ContainerBuilder $container): void
    {
        $block = static fn (string $template, int $priority): array => [
            'template' => sprintf('@SetonoSyliusCMSPlugin/admin/entry/show/%s.html.twig', $template),
            'priority' => $priority,
        ];

        $container->prependExtensionConfig('sylius_ui', [
            'events' => [
                'setono_sylius_cms.admin.entry.show.header_actions' => [
                    'blocks' => [
                        'open_in_builder' => $block('_open_in_builder', 20),
                        'share_draft' => $block('_share_draft', 10),
                    ],
                ],
                'setono_sylius_cms.admin.entry.show.sections' => [
                    'blocks' => [
                        'publishing' => $block('_publishing', 100),
                        'problems' => $block('_problems', 90),
                        'where_it_shows' => $block('_where_it_shows', 70),
                        'revisions' => $block('_revisions', 60),
                        'share_links' => $block('_share_links', 50),
                        'urls' => $block('_urls', 40),
                        'translations' => $block('_translations', 20),
                    ],
                ],
            ],
        ]);
    }

    /**
     * The admin's entry lists, as Sylius grids of the entry resource, the way Sylius plugins ship grids: All entries
     * (setono_sylius_cms_admin_entry_all) and each content type's list (setono_sylius_cms_admin_entry), which gets the
     * content type's code from the route. Projects change them in their own sylius_grid config. The fields are spaced
     * by position, so a column can go between two. See "Entry lists" in docs/spec/admin.md
     */
    private function prependGrids(ContainerBuilder $container): void
    {
        $field = static fn (string $label, string $template, int $position): array => [
            'type' => 'twig',
            'label' => $label,
            'path' => '.',
            'position' => $position,
            'options' => ['template' => sprintf('@SetonoSyliusCMSPlugin/admin/entry/grid/field/%s.html.twig', $template)],
        ];

        $container->prependExtensionConfig('sylius_grid', [
            'grids' => [
                'setono_sylius_cms_admin_entry' => [
                    'driver' => [
                        'name' => 'doctrine/orm',
                        'options' => [
                            'class' => '%setono_sylius_cms.model.entry.class%',
                            // The route's content type code, which All entries' route doesn't have
                            'repository' => ['method' => 'createListQueryBuilder', 'arguments' => ['$contentType']],
                        ],
                    ],
                    'sorting' => self::GRID_SORTING,
                    'limits' => self::GRID_LIMITS,
                    'fields' => [
                        'title' => $field('setono_sylius_cms.ui.title', 'title', 10),
                        'status' => $field('setono_sylius_cms.ui.status', 'status', 30),
                        'translations' => $field('setono_sylius_cms.ui.translations', 'translations', 40),
                        'lastEdited' => ['sortable' => 'lastEditedAt'] + $field('setono_sylius_cms.ui.last_edited', 'last_edited', 50),
                        'problems' => $field('setono_sylius_cms.ui.problems', 'problems', 60),
                    ],
                    'filters' => [
                        'search' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_cms.ui.search',
                            'options' => ['fields' => ['translation.title', 'translation.slug']],
                            'form_options' => ['type' => 'contains'],
                        ],
                        'status' => ['type' => StatusFilter::TYPE, 'label' => 'setono_sylius_cms.ui.status'],
                        'channel' => ['type' => ChannelFilter::TYPE, 'label' => 'setono_sylius_cms.ui.channel'],
                    ],
                    'actions' => [
                        'main' => [
                            // The create dialog, for the route's content type, or asking for one in All entries
                            'create' => [
                                'type' => self::CREATE_ENTRY_ACTION,
                                'options' => ['contentType' => '$contentType'],
                            ],
                        ],
                        'item' => [
                            'delete' => [
                                'type' => 'delete',
                                'options' => ['link' => ['route' => 'setono_sylius_cms_admin_entry_delete', 'parameters' => ['uuid' => 'resource.uuid']]],
                            ],
                        ],
                    ],
                ],
                'setono_sylius_cms_admin_entry_all' => [
                    'extends' => 'setono_sylius_cms_admin_entry',
                    // A grid that extends another doesn't take its sorting and limits
                    'sorting' => self::GRID_SORTING,
                    'limits' => self::GRID_LIMITS,
                    'fields' => [
                        'type' => $field('setono_sylius_cms.ui.type', 'type', 20),
                    ],
                ],
            ],
            'templates' => [
                'action' => [
                    self::CREATE_ENTRY_ACTION => '@SetonoSyliusCMSPlugin/admin/entry/grid/action/create.html.twig',
                ],
                'filter' => [
                    StatusFilter::TYPE => '@SyliusUi/Grid/Filter/select.html.twig',
                    ChannelFilter::TYPE => '@SyliusUi/Grid/Filter/select.html.twig',
                ],
            ],
        ]);
    }
}
