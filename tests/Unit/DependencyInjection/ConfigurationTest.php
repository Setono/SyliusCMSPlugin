<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\Configuration;
use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
use Setono\SyliusCMSPlugin\Factory\HomepageFactory;
use Setono\SyliusCMSPlugin\Factory\PlacementFactory;
use Setono\SyliusCMSPlugin\Factory\ShareLinkFactory;
use Setono\SyliusCMSPlugin\Model\BestsellerScore;
use Setono\SyliusCMSPlugin\Model\BestsellerScoreInterface;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\EntryPresenceInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemTranslation;
use Setono\SyliusCMSPlugin\Model\MediaItemTranslationInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsage;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Setono\SyliusCMSPlugin\Model\OldSlug;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;
use Setono\SyliusCMSPlugin\Model\SavedChangeSetInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepository;
use Setono\SyliusCMSPlugin\Repository\EntryRepository;
use Setono\SyliusCMSPlugin\Repository\HomepageRepository;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepository;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepository;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepository;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepository;
use Setono\SyliusCMSPlugin\Repository\PlacementRepository;
use Setono\SyliusCMSPlugin\Repository\RevisionRepository;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepository;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepository;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Factory\TranslatableFactory;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyConfigTest
 */
final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @test
     */
    public function it_declares_the_entry_page_snippet_the_draft_and_the_rows_beside_an_entry_as_resources_by_default(): void
    {
        $this->assertProcessedConfigurationEquals([
            [],
        ], [
            'disabled' => ['block_types' => [], 'layouts' => [], 'sources' => [], 'presets' => []],
            'embed_code' => false,
            'assets' => true,
            'content_types' => [],
            'slots' => [],
            'context_keys' => [],
            'bindable_properties' => [],
            'block_styles' => [],
            'fallback_locales' => [],
            'revisions' => ['keep' => 50],
            'media' => [
                'storage' => 'setono_sylius_cms.storage',
                'types' => [
                    'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'],
                    'video' => ['video/mp4', 'video/webm'],
                    'document' => ['application/pdf'],
                ],
                'max_upload_size' => null,
                'grace_period' => 'P1D',
                'liip' => ['loader' => 'setono_sylius_cms', 'resolver' => 'setono_sylius_cms'],
                'widths' => [320, 640, 960, 1280, 1600, 1920, 2560],
            ],
            'bestsellers' => ['window' => 'P30D'],
            'builder' => ['scripts' => []],
            'presets' => ['paths' => []],
            'resources' => [
                'entry' => [
                    'classes' => [
                        'model' => Entry::class,
                        'interface' => EntryInterface::class,
                        'controller' => ResourceController::class,
                        'repository' => EntryRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'draft' => [
                    'classes' => [
                        'model' => Draft::class,
                        'interface' => DraftInterface::class,
                        'repository' => DraftRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'entry_channel' => [
                    'classes' => [
                        'model' => EntryChannel::class,
                        'interface' => EntryChannelInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => EntryChannelFactory::class,
                    ],
                ],
                'entry_audience_group' => [
                    'classes' => [
                        'model' => EntryAudienceGroup::class,
                        'interface' => EntryAudienceGroupInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => EntryAudienceGroupFactory::class,
                    ],
                ],
                'entry_presence' => [
                    'classes' => [
                        'model' => EntryPresence::class,
                        'interface' => EntryPresenceInterface::class,
                        'repository' => EntryPresenceRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'saved_change_set' => [
                    'classes' => [
                        'model' => SavedChangeSet::class,
                        'interface' => SavedChangeSetInterface::class,
                        'repository' => SavedChangeSetRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'revision' => [
                    'classes' => [
                        'model' => Revision::class,
                        'interface' => RevisionInterface::class,
                        'repository' => RevisionRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'old_slug' => [
                    'classes' => [
                        'model' => OldSlug::class,
                        'interface' => OldSlugInterface::class,
                        'repository' => OldSlugRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'scheduled_publish' => [
                    'classes' => [
                        'model' => ScheduledPublish::class,
                        'interface' => ScheduledPublishInterface::class,
                        'repository' => ScheduledPublishRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'share_link' => [
                    'classes' => [
                        'model' => ShareLink::class,
                        'interface' => ShareLinkInterface::class,
                        'repository' => ShareLinkRepository::class,
                        'factory' => ShareLinkFactory::class,
                    ],
                ],
                'homepage' => [
                    'classes' => [
                        'model' => Homepage::class,
                        'interface' => HomepageInterface::class,
                        'repository' => HomepageRepository::class,
                        'factory' => HomepageFactory::class,
                    ],
                ],
                'media_item' => [
                    'classes' => [
                        'model' => MediaItem::class,
                        'interface' => MediaItemInterface::class,
                        'repository' => MediaItemRepository::class,
                        'factory' => TranslatableFactory::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => MediaItemTranslation::class,
                            'interface' => MediaItemTranslationInterface::class,
                            'factory' => Factory::class,
                        ],
                    ],
                ],
                'media_folder' => [
                    'classes' => [
                        'model' => MediaFolder::class,
                        'interface' => MediaFolderInterface::class,
                        'repository' => MediaFolderRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'media_usage' => [
                    'classes' => [
                        'model' => MediaUsage::class,
                        'interface' => MediaUsageInterface::class,
                        'repository' => MediaUsageRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'bestseller_score' => [
                    'classes' => [
                        'model' => BestsellerScore::class,
                        'interface' => BestsellerScoreInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
                'placement' => [
                    'classes' => [
                        'model' => Placement::class,
                        'interface' => PlacementInterface::class,
                        'repository' => PlacementRepository::class,
                        'factory' => PlacementFactory::class,
                    ],
                ],
                'page' => [
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
                ],
                'snippet' => [
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
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function it_lets_a_project_set_the_media_storage_the_mime_types_of_a_kind_and_the_upload_limit(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['media' => ['storage' => 'app.s3_storage', 'types' => ['document' => ['application/pdf', 'application/zip']], 'max_upload_size' => '20M']],
        ], ['media' => [
            'storage' => 'app.s3_storage',
            'types' => [
                'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'],
                'video' => ['video/mp4', 'video/webm'],
                'document' => ['application/pdf', 'application/zip'],
            ],
            'max_upload_size' => 20 * 1024 * 1024,
            'grace_period' => 'P1D',
            'liip' => ['loader' => 'setono_sylius_cms', 'resolver' => 'setono_sylius_cms'],
            'widths' => [320, 640, 960, 1280, 1600, 1920, 2560],
        ]], 'media');
    }

    /**
     * @test
     */
    public function it_lets_a_project_name_the_liip_loader_and_resolver_its_images_are_processed_with(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['media' => ['liip' => ['loader' => 'app_s3', 'resolver' => 'app_s3_cache']]],
        ], ['media' => ['liip' => ['loader' => 'app_s3', 'resolver' => 'app_s3_cache']]], 'media.liip');
    }

    /**
     * @test
     */
    public function it_takes_the_widths_of_the_image_variants_in_ascending_order_each_once(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['media' => ['widths' => [1200, 400, 800, 400]]],
        ], ['media' => ['widths' => [400, 800, 1200]]], 'media.widths');
    }

    /**
     * @test
     */
    public function it_refuses_a_width_that_is_not_a_positive_number_of_pixels(): void
    {
        $this->assertConfigurationIsInvalid([['media' => ['widths' => [640, 0]]]], 'An image width must be a positive number of pixels');
        $this->assertConfigurationIsInvalid([['media' => ['widths' => ['wide']]]], 'An image width must be a positive number of pixels');
    }

    /**
     * @test
     */
    public function it_refuses_no_widths(): void
    {
        $this->assertConfigurationIsInvalid([['media' => ['widths' => []]]], 'should have at least 1 element');
    }

    /**
     * @test
     */
    public function it_lets_a_project_set_the_grace_period_before_a_replaced_file_is_deleted(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['media' => ['grace_period' => 'PT12H']],
        ], ['media' => ['grace_period' => 'PT12H']], 'media.grace_period');
    }

    /**
     * @test
     */
    public function it_refuses_a_grace_period_that_is_not_an_iso_8601_duration(): void
    {
        $this->assertConfigurationIsInvalid([['media' => ['grace_period' => '1 day']]], '"1 day" isn\'t an ISO 8601 duration, such as P1D');
    }

    /**
     * @test
     */
    public function it_takes_the_upload_limit_in_bytes(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['media' => ['max_upload_size' => 1500000]],
        ], ['media' => ['max_upload_size' => 1500000]], 'media.max_upload_size');
    }

    /**
     * @test
     */
    public function it_refuses_an_upload_limit_that_is_not_a_size(): void
    {
        $this->assertConfigurationIsInvalid([['media' => ['max_upload_size' => 'lots']]], 'must be a positive number of bytes');
    }

    /**
     * @test
     */
    public function it_refuses_what_is_not_a_mime_type(): void
    {
        $this->assertConfigurationIsInvalid([['media' => ['types' => ['document' => ['pdf']]]]], '"pdf" isn\'t a MIME type');
    }

    /**
     * An uploaded file of it would have no one kind
     *
     * @test
     */
    public function it_refuses_a_mime_type_in_two_kinds(): void
    {
        $this->assertConfigurationIsInvalid([['media' => ['types' => ['document' => ['image/png']]]]], 'The MIME type "image/png" is in both');
    }

    /**
     * @test
     */
    public function it_lets_a_project_switch_off_shipped_block_types_by_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['disabled' => ['block_types' => ['spacer']]],
            ['disabled' => ['block_types' => ['group']]],
        ], ['disabled' => ['block_types' => ['spacer', 'group'], 'layouts' => [], 'sources' => [], 'presets' => []]], 'disabled');
    }

    /**
     * @test
     */
    public function it_lets_a_project_switch_off_layouts_by_content_type_and_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['disabled' => ['layouts' => ['page.landing']]],
            ['disabled' => ['layouts' => ['blog_post.wide']]],
        ], ['disabled' => ['block_types' => [], 'layouts' => ['page.landing', 'blog_post.wide'], 'sources' => [], 'presets' => []]], 'disabled');
    }

    /**
     * @test
     */
    public function it_lets_a_project_switch_off_shipped_sources_by_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['disabled' => ['sources' => ['newest_products']]],
            ['disabled' => ['sources' => ['taxon_children']]],
        ], ['disabled' => ['block_types' => [], 'layouts' => [], 'sources' => ['newest_products', 'taxon_children'], 'presets' => []]], 'disabled');
    }

    /**
     * @test
     */
    public function it_lets_a_project_switch_off_code_presets_by_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['disabled' => ['presets' => ['hero']]],
            ['disabled' => ['presets' => ['faq']]],
        ], ['disabled' => ['block_types' => [], 'layouts' => [], 'sources' => [], 'presets' => ['hero', 'faq']]], 'disabled');
    }

    /**
     * @test
     */
    public function it_takes_the_directories_of_code_preset_files_that_bundles_prepend_and_a_project_adds(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['presets' => ['paths' => ['%kernel.project_dir%/vendor/acme/cms-presets/presets']]],
            ['presets' => ['paths' => ['%kernel.project_dir%/config/cms/presets']]],
        ], ['presets' => ['paths' => ['%kernel.project_dir%/vendor/acme/cms-presets/presets', '%kernel.project_dir%/config/cms/presets']]], 'presets');
    }

    /**
     * @test
     */
    public function it_refuses_an_empty_directory_of_code_preset_files(): void
    {
        $this->assertConfigurationIsInvalid([['presets' => ['paths' => ['']]]], 'cannot contain an empty value');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidDisabledLayouts
     */
    public function it_refuses_a_disabled_layout_that_is_not_a_content_type_code_and_a_layout_code(int|string $layout): void
    {
        $this->assertConfigurationIsInvalid([['disabled' => ['layouts' => [$layout]]]], sprintf('A disabled layout must be "<content type code>.<layout code>", such as page.standard, not "%s"', $layout));
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public function provideInvalidDisabledLayouts(): iterable
    {
        yield 'a number' => [42];
        yield 'a layout code alone' => ['landing'];
        yield 'a content type code that is no code' => ['Page.landing'];
        yield 'a layout code that is no code' => ['page.Landing'];
        yield 'three parts' => ['page.landing.wide'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_change_a_content_types_default_layout(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['content_types' => ['page' => ['default_layout' => 'landing']]],
            ['content_types' => ['blog_post' => ['default_layout' => 'wide']]],
        ], ['content_types' => [
            'page' => ['default_layout' => 'landing'],
            'blog_post' => ['default_layout' => 'wide'],
        ]], 'content_types');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidDefaultLayouts
     *
     * @param array<array-key, mixed> $contentTypes
     */
    public function it_refuses_an_invalid_default_layout_or_code(array $contentTypes, string $message): void
    {
        $this->assertConfigurationIsInvalid([['content_types' => $contentTypes]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidDefaultLayouts(): iterable
    {
        yield 'a content type code that is no code' => [['blog-post' => ['default_layout' => 'wide']], 'The content type code "blog-post" must match ^[a-z][a-z0-9_]*$'];
        yield 'a default layout that is no code' => [['page' => ['default_layout' => 'Landing']], 'The layout code "Landing" must match ^[a-z][a-z0-9_]*$'];
        yield 'an empty default layout' => [['page' => ['default_layout' => '']], 'cannot contain an empty value'];
        yield 'a numeric default layout' => [['page' => ['default_layout' => 42]], 'The layout code "42" must match ^[a-z][a-z0-9_]*$'];
        yield 'a numeric content type code' => [[42 => ['default_layout' => 'wide']], 'The content type code "42" must match ^[a-z][a-z0-9_]*$'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_opt_in_to_the_embed_code_block(): void
    {
        $this->assertProcessedConfigurationEquals([['embed_code' => true]], ['embed_code' => true], 'embed_code');
    }

    /**
     * @test
     */
    public function it_takes_a_boolean_for_embed_code(): void
    {
        $this->assertConfigurationIsInvalid([['embed_code' => 'yes']], 'Invalid type for path "setono_sylius_cms.embed_code"');
    }

    /**
     * @test
     */
    public function it_lets_a_project_stop_loading_the_shipped_stylesheet_and_carousel_script(): void
    {
        $this->assertProcessedConfigurationEquals([['assets' => false]], ['assets' => false], 'assets');
    }

    /**
     * @test
     */
    public function it_takes_a_boolean_for_assets(): void
    {
        $this->assertConfigurationIsInvalid([['assets' => 'no']], 'Invalid type for path "setono_sylius_cms.assets"');
    }

    /**
     * @test
     */
    public function it_lets_a_project_add_block_styles_to_any_block_type_by_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['block_styles' => ['banner' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-banner--brand']]]],
            ['block_styles' => [
                'banner' => ['outlined' => ['label' => 'app.cms.block_style.outlined', 'class' => 'app-banner--outlined']],
                'group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']],
            ]],
        ], ['block_styles' => [
            'banner' => [
                'brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-banner--brand'],
                'outlined' => ['label' => 'app.cms.block_style.outlined', 'class' => 'app-banner--outlined'],
            ],
            'group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']],
        ]], 'block_styles');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidBlockStyles
     *
     * @param array<array-key, mixed> $blockStyles
     */
    public function it_refuses_an_invalid_block_style(array $blockStyles, string $message): void
    {
        $this->assertConfigurationIsInvalid([['block_styles' => $blockStyles]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidBlockStyles(): iterable
    {
        yield 'no label' => [['group' => ['brand' => ['class' => 'app-group--brand']]], 'The child config "label" under "setono_sylius_cms.block_styles.group.brand" must be configured'];
        yield 'no class' => [['group' => ['brand' => ['label' => 'app.brand']]], 'The child config "class" under "setono_sylius_cms.block_styles.group.brand" must be configured'];
        yield 'an empty label' => [['group' => ['brand' => ['label' => '', 'class' => 'app-group--brand']]], 'cannot contain an empty value'];
        yield 'an empty class' => [['group' => ['brand' => ['label' => 'app.brand', 'class' => '']]], 'cannot contain an empty value'];
        yield 'a style code that is no code' => [['group' => ['Brand' => ['label' => 'app.brand', 'class' => 'app-group--brand']]], 'The block style code "Brand" must match ^[a-z][a-z0-9_]*$'];
        yield 'a block type code that is no code' => [['app-banner' => ['brand' => ['label' => 'app.brand', 'class' => 'app-banner--brand']]], 'The block type code "app-banner" must match ^[a-z][a-z0-9_]*$'];
        yield 'a style code with a hyphen' => [['group' => ['dark-blue' => ['label' => 'app.dark_blue', 'class' => 'app-group--dark-blue']]], 'The block style code "dark-blue" must match'];
        yield 'a numeric style code' => [['group' => [42 => ['label' => 'app.brand', 'class' => 'app-group--brand']]], 'The block style code "42" must match'];
        yield 'a numeric block type code' => [[42 => ['brand' => ['label' => 'app.brand', 'class' => 'app-group--brand']]], 'The block type code "42" must match'];
    }

    /**
     * @test
     */
    public function a_project_extends_page_by_overriding_its_classes(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['page' => [
                'options' => ['foo' => 'bar'],
                'classes' => ['model' => 'App\Entity\Page', 'repository' => 'App\Repository\PageRepository'],
                'translation' => ['classes' => ['model' => 'App\Entity\PageTranslation']],
            ]]],
        ], [
            'resources' => [
                'page' => [
                    'options' => ['foo' => 'bar'],
                    'classes' => [
                        'model' => 'App\Entity\Page',
                        'repository' => 'App\Repository\PageRepository',
                        'interface' => PageInterface::class,
                        'controller' => ResourceController::class,
                        'factory' => TranslatableFactory::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => 'App\Entity\PageTranslation',
                            'interface' => PageTranslationInterface::class,
                            'controller' => ResourceController::class,
                            'factory' => Factory::class,
                        ],
                    ],
                ],
            ],
        ], 'resources.page');
    }

    /**
     * @test
     */
    public function it_lets_a_project_set_the_locales_a_locale_falls_back_through_in_order(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['fallback_locales' => ['de_CH' => ['de_DE']]],
            ['fallback_locales' => ['fr_CH' => ['fr_FR', 'de_DE']]],
        ], ['fallback_locales' => [
            'de_CH' => ['de_DE'],
            'fr_CH' => ['fr_FR', 'de_DE'],
        ]], 'fallback_locales');
    }

    /**
     * @test
     */
    public function it_lets_a_later_config_replace_the_fallback_locales_of_a_locale(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['fallback_locales' => ['fr_CH' => ['fr_FR', 'de_DE']]],
            ['fallback_locales' => ['fr_CH' => ['de_DE']]],
        ], ['fallback_locales' => ['fr_CH' => ['de_DE']]], 'fallback_locales');
    }

    /**
     * @test
     */
    public function it_refuses_an_empty_fallback_locale(): void
    {
        $this->assertConfigurationIsInvalid([['fallback_locales' => ['de_CH' => ['']]]], 'The path "setono_sylius_cms.fallback_locales.de_CH.0" cannot contain an empty value');
    }

    /**
     * @test
     */
    public function it_lets_a_project_change_the_url_prefix_of_a_content_type_by_its_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['content_types' => ['page' => ['prefix' => 'pg'], 'blog_post' => ['prefix' => 'news/all']]],
            ['content_types' => ['app_store' => ['prefix' => '']]],
        ], ['content_types' => [
            'page' => ['prefix' => 'pg'],
            'blog_post' => ['prefix' => 'news/all'],
            'app_store' => ['prefix' => ''],
        ]], 'content_types');
    }

    /**
     * @test
     */
    public function it_leaves_out_a_prefix_a_project_does_not_set(): void
    {
        $this->assertProcessedConfigurationEquals([['content_types' => ['page' => []]]], ['content_types' => ['page' => []]], 'content_types');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidContentTypes
     *
     * @param array<array-key, mixed> $contentTypes
     */
    public function it_refuses_an_invalid_content_type_prefix_or_code(array $contentTypes, string $message): void
    {
        $this->assertConfigurationIsInvalid([['content_types' => $contentTypes]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidContentTypes(): iterable
    {
        yield 'a slash first' => [['page' => ['prefix' => '/pg']], 'The prefix "/pg" must be empty or a string that doesn\'t start or end with a slash or contain two slashes in a row'];
        yield 'a slash last' => [['page' => ['prefix' => 'pg/']], 'The prefix "pg/" must be empty'];
        yield 'two slashes in a row' => [['page' => ['prefix' => 'a//b']], 'The prefix "a//b" must be empty'];
        yield 'not a string' => [['page' => ['prefix' => 7]], 'The prefix int must be empty'];
        yield 'an invalid code' => [['Page' => ['prefix' => 'pg']], 'The content type code "Page" must match ^[a-z][a-z0-9_]*$'];
        yield 'a number as a code' => [[7 => ['prefix' => 'pg']], 'The content type code "7" must match ^[a-z][a-z0-9_]*$'];
        yield 'a dash in the code, which is kept' => [['blog-post' => ['prefix' => 'pg']], 'The content type code "blog-post" must match ^[a-z][a-z0-9_]*$'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_declare_slots_by_identifier_with_a_label_the_context_keys_they_provide_a_cap_and_a_template_event(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['slots' => [
                'app.store_locator.top' => [
                    'label' => 'app.cms.slot.store_locator_top',
                    'provides' => ['store_location'],
                    'cap' => 1,
                    'template_event' => ['name' => 'app.store_locator.top', 'priority' => 10],
                ],
                'page.landing.after_hero' => ['label' => 'app.cms.slot.after_hero'],
                'app.header' => ['label' => 'app.cms.slot.header', 'template_event' => ['name' => 'sylius.shop.layout.header']],
            ]],
        ], ['slots' => [
            'app.store_locator.top' => [
                'label' => 'app.cms.slot.store_locator_top',
                'provides' => ['store_location'],
                'cap' => 1,
                'template_event' => ['name' => 'app.store_locator.top', 'priority' => 10],
            ],
            'page.landing.after_hero' => ['label' => 'app.cms.slot.after_hero', 'provides' => [], 'cap' => null],
            'app.header' => ['label' => 'app.cms.slot.header', 'template_event' => ['name' => 'sylius.shop.layout.header', 'priority' => 0], 'provides' => [], 'cap' => null],
        ]], 'slots');
    }

    /**
     * @test
     */
    public function it_lets_a_later_config_cap_a_slot_an_earlier_one_declared_and_replace_the_keys_it_provides(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['slots' => ['shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => ['product'], 'template_event' => ['name' => 'sylius.shop.layout.header', 'priority' => 30]]]],
            ['slots' => ['shop.top' => ['cap' => 1, 'provides' => ['taxon']]]],
        ], ['slots' => [
            'shop.top' => [
                'label' => 'setono_sylius_cms.slot.shop.top',
                'provides' => ['taxon'],
                'template_event' => ['name' => 'sylius.shop.layout.header', 'priority' => 30],
                'cap' => 1,
            ],
        ]], 'slots');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidSlots
     *
     * @param array<array-key, mixed> $slots
     */
    public function it_refuses_an_invalid_slot(array $slots, string $message): void
    {
        $this->assertConfigurationIsInvalid([['slots' => $slots]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidSlots(): iterable
    {
        yield 'an identifier with a capital' => [['Shop.top' => ['label' => 'a']], 'The slot identifier "Shop.top" must be codes joined by dots, such as product.after_price'];
        yield 'an identifier with a hyphen' => [['shop.after-price' => ['label' => 'a']], 'The slot identifier "shop.after-price" must be codes joined by dots, such as product.after_price'];
        yield 'an identifier ending in a dot' => [['shop.' => ['label' => 'a']], 'The slot identifier "shop." must be codes joined by dots, such as product.after_price'];
        yield 'no label' => [['shop.top' => []], 'The child config "label" under "setono_sylius_cms.slots.shop.top" must be configured'];
        yield 'a cap of none' => [['shop.top' => ['label' => 'a', 'cap' => 0]], 'The value 0 is too small for path "setono_sylius_cms.slots.shop.top.cap"'];
        yield 'a context key that is no code' => [['shop.top' => ['label' => 'a', 'provides' => ['Product']]], 'The context key code "Product" must match'];
        yield 'a template event without a name' => [['shop.top' => ['label' => 'a', 'template_event' => ['priority' => 1]]], 'The child config "name" under "setono_sylius_cms.slots.shop.top.template_event" must be configured'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_declare_context_keys_with_the_class_of_their_object_and_a_label(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['context_keys' => ['store_location' => ['class' => \stdClass::class, 'label' => 'app.cms.context_key.store_location']]],
            ['context_keys' => ['brand' => ['class' => \Countable::class, 'label' => 'app.cms.context_key.brand']]],
        ], ['context_keys' => [
            'store_location' => ['class' => \stdClass::class, 'label' => 'app.cms.context_key.store_location'],
            'brand' => ['class' => \Countable::class, 'label' => 'app.cms.context_key.brand'],
        ]], 'context_keys');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidContextKeys
     *
     * @param array<array-key, mixed> $contextKeys
     */
    public function it_refuses_an_invalid_context_key(array $contextKeys, string $message): void
    {
        $this->assertConfigurationIsInvalid([['context_keys' => $contextKeys]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidContextKeys(): iterable
    {
        yield 'a key that is no code' => [['Brand' => ['class' => \stdClass::class, 'label' => 'a']], 'The context key code "Brand" must match'];
        yield 'no class' => [['brand' => ['label' => 'a']], 'The child config "class" under "setono_sylius_cms.context_keys.brand" must be configured'];
        yield 'a class that does not exist' => [['brand' => ['class' => 'App\\Entity\\Gone', 'label' => 'a']], 'The class "App\\Entity\\Gone" of a context key is no class or interface'];
        yield 'no label' => [['brand' => ['class' => \stdClass::class]], 'The child config "label" under "setono_sylius_cms.context_keys.brand" must be configured'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_declare_bindable_properties_that_are_property_paths_by_context_key_and_code(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['bindable_properties' => ['product' => ['brand' => ['label' => 'app.cms.binding.brand', 'type' => 'text', 'path' => 'brand.name']]]],
            ['bindable_properties' => ['product' => ['weight' => ['label' => 'app.cms.binding.weight', 'type' => 'number', 'path' => 'variants[0].weight']], 'channel' => ['slogan' => ['label' => 'app.cms.binding.slogan', 'type' => 'text', 'path' => 'slogan']]]],
        ], ['bindable_properties' => [
            'product' => [
                'brand' => ['label' => 'app.cms.binding.brand', 'type' => 'text', 'path' => 'brand.name'],
                'weight' => ['label' => 'app.cms.binding.weight', 'type' => 'number', 'path' => 'variants[0].weight'],
            ],
            'channel' => ['slogan' => ['label' => 'app.cms.binding.slogan', 'type' => 'text', 'path' => 'slogan']],
        ]], 'bindable_properties');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidBindableProperties
     *
     * @param array<array-key, mixed> $properties
     */
    public function it_refuses_an_invalid_bindable_property(array $properties, string $message): void
    {
        $this->assertConfigurationIsInvalid([['bindable_properties' => $properties]], $message);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public function provideInvalidBindableProperties(): iterable
    {
        yield 'a context key that is no code' => [['Product' => ['brand' => ['label' => 'a', 'type' => 'text', 'path' => 'brand']]], 'The context key code "Product" must match'];
        yield 'a code that is no code' => [['product' => ['Brand' => ['label' => 'a', 'type' => 'text', 'path' => 'brand']]], 'The bindable property code "Brand" must match'];
        yield 'a type that is no binding type' => [['product' => ['brand' => ['label' => 'a', 'type' => 'image', 'path' => 'brand']]], 'The value "image" is not allowed for path "setono_sylius_cms.bindable_properties.product.brand.type"'];
        yield 'no label' => [['product' => ['brand' => ['type' => 'text', 'path' => 'brand']]], 'The child config "label" under "setono_sylius_cms.bindable_properties.product.brand" must be configured'];
        yield 'no path' => [['product' => ['brand' => ['label' => 'a', 'type' => 'text']]], 'The child config "path" under "setono_sylius_cms.bindable_properties.product.brand" must be configured'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_set_how_many_revisions_per_entry_are_kept(): void
    {
        $this->assertProcessedConfigurationEquals([['revisions' => ['keep' => 10]]], ['revisions' => ['keep' => 10]], 'revisions');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidRevisionsToKeep
     */
    public function it_keeps_at_least_one_revision_per_entry(mixed $keep, string $message): void
    {
        $this->assertConfigurationIsInvalid([['revisions' => ['keep' => $keep]]], $message);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public function provideInvalidRevisionsToKeep(): iterable
    {
        yield 'none' => [0, 'Should be greater than or equal to 1'];
        yield 'not a number' => ['all', 'Invalid type for path "setono_sylius_cms.revisions.keep"'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_set_the_window_the_default_bestsellers_calculator_counts_paid_orders_over(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['bestsellers' => ['window' => 'P7D']],
        ], ['bestsellers' => ['window' => 'P7D']], 'bestsellers');
    }

    /**
     * @test
     */
    public function it_refuses_a_bestsellers_window_that_is_not_an_iso_8601_duration(): void
    {
        $this->assertConfigurationIsInvalid([['bestsellers' => ['window' => '30 days']]], '"30 days" isn\'t an ISO 8601 duration, such as P1D');
    }

    /**
     * @test
     */
    public function it_lets_a_project_list_the_scripts_of_its_field_controls_as_asset_paths_public_paths_or_absolute_urls(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['builder' => ['scripts' => ['/build/app/cms/opening-hours.js', 'build/app/cms/rating.js']]],
            ['builder' => ['scripts' => ['https://cdn.example.com/cms/map.js', '/build/app/cms/opening-hours.js']]],
        ], ['builder' => ['scripts' => ['/build/app/cms/opening-hours.js', 'build/app/cms/rating.js', 'https://cdn.example.com/cms/map.js']]], 'builder');
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidScripts
     */
    public function it_refuses_a_script_that_is_neither_an_asset_path_a_public_path_nor_an_absolute_url(string $script): void
    {
        $this->assertConfigurationIsInvalid([['builder' => ['scripts' => [$script]]]], sprintf('"%s" is neither an asset path, such as build/app/cms/controls.js, a public path, such as /cms/controls.js, nor an absolute http(s) URL', $script));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function provideInvalidScripts(): iterable
    {
        yield 'empty' => [''];
        yield 'with a space' => ['build/app/cms controls.js'];
        yield 'another scheme' => ['javascript:alert(1)'];
        yield 'a protocol-relative URL' => ['//cdn.example.com/controls.js'];
    }

    /**
     * @test
     */
    public function it_lets_a_project_override_the_draft_model(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['draft' => ['classes' => ['model' => 'App\Entity\Draft']]]],
        ], [
            'resources' => [
                'draft' => [
                    'classes' => [
                        'model' => 'App\Entity\Draft',
                        'interface' => DraftInterface::class,
                        'repository' => DraftRepository::class,
                        'factory' => Factory::class,
                    ],
                ],
            ],
        ], 'resources.draft');
    }

    /**
     * A project's own row models are created by the shipped factories, which create the configured model
     *
     * @test
     */
    public function it_lets_a_project_override_the_models_of_the_channel_and_audience_group_rows(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['entry_channel' => ['classes' => ['model' => 'App\\Entity\\EntryChannel']]]],
        ], [
            'resources' => [
                'entry_channel' => [
                    'classes' => [
                        'model' => 'App\\Entity\\EntryChannel',
                        'interface' => EntryChannelInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => EntryChannelFactory::class,
                    ],
                ],
            ],
        ], 'resources.entry_channel');
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['entry_audience_group' => ['classes' => ['model' => 'App\\Entity\\EntryAudienceGroup']]]],
        ], [
            'resources' => [
                'entry_audience_group' => [
                    'classes' => [
                        'model' => 'App\\Entity\\EntryAudienceGroup',
                        'interface' => EntryAudienceGroupInterface::class,
                        'repository' => EntityRepository::class,
                        'factory' => EntryAudienceGroupFactory::class,
                    ],
                ],
            ],
        ], 'resources.entry_audience_group');
        $this->assertProcessedConfigurationEquals([
            ['resources' => ['homepage' => ['classes' => ['model' => 'App\\Entity\\Homepage']]]],
        ], [
            'resources' => [
                'homepage' => [
                    'classes' => [
                        'model' => 'App\\Entity\\Homepage',
                        'interface' => HomepageInterface::class,
                        'repository' => HomepageRepository::class,
                        'factory' => HomepageFactory::class,
                    ],
                ],
            ],
        ], 'resources.homepage');
    }

    /**
     * @test
     *
     * @dataProvider provideEmptyClasses
     *
     * @param array<string, mixed> $config
     */
    public function it_refuses_an_empty_class(array $config): void
    {
        $this->assertConfigurationIsInvalid([['resources' => $config]], 'cannot contain an empty value');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public function provideEmptyClasses(): iterable
    {
        foreach (['model', 'interface', 'controller', 'repository', 'factory'] as $class) {
            yield $class => [['page' => ['classes' => [$class => '']]]];
            yield 'translation ' . $class => [['page' => ['translation' => ['classes' => [$class => '']]]]];
        }

        foreach (['model', 'interface', 'repository', 'factory'] as $class) {
            yield 'draft ' . $class => [['draft' => ['classes' => [$class => '']]]];
        }
    }
}
