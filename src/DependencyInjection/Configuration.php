<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
use Setono\SyliusCMSPlugin\Factory\HomepageFactory;
use Setono\SyliusCMSPlugin\Factory\ShareLinkFactory;
use Setono\SyliusCMSPlugin\Media\FileSize;
use Setono\SyliusCMSPlugin\Media\GracePeriod;
use Setono\SyliusCMSPlugin\Media\MediaKind;
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
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;
use Setono\SyliusCMSPlugin\Model\SavedChangeSetInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepository;
use Setono\SyliusCMSPlugin\Repository\EntryRepository;
use Setono\SyliusCMSPlugin\Repository\HomepageRepository;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepository;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepository;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepository;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepository;
use Setono\SyliusCMSPlugin\Repository\RevisionRepository;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepository;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepository;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepository;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Setono\SyliusCMSPlugin\Time\Duration;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Factory\TranslatableFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private const MIME_TYPE = '#^[a-z0-9][a-z0-9!\#$&^_.+-]*/[a-z0-9][a-z0-9!\#$&^_.+-]*$#i';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_cms');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('disabled')
                    ->info('Shipped items to switch off. Blocks of a disabled block type already in trees keep rendering')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('block_types')
                            ->info('Block type codes')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('layouts')
                            ->info('"<content type code>.<layout code>", such as page.standard. Their entries render in the default layout, which can\'t be disabled')
                            ->scalarPrototype()
                                ->validate()->always(self::assertDisabledLayout(...))->end()
                            ->end()
                        ->end()
                        ->arrayNode('sources')
                            ->info('Source codes. Editors aren\'t offered them, and the lists they fill already keep resolving through them')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('assets')
                    ->info('false stops loading the shipped stylesheet and carousel script, for projects that bundle them into their own build')
                    ->defaultTrue()
                ->end()
            ->end()
        ;

        $this->addBlockStylesSection($rootNode);
        $this->addContentTypesSection($rootNode);
        $this->addFallbackLocalesSection($rootNode);
        $this->addRevisionsSection($rootNode);
        $this->addMediaSection($rootNode);
        $this->addBestsellersSection($rootNode);
        $this->addBuilderSection($rootNode);
        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Block styles added to any block type, shipped or not, by block type code and style code. See "Block styles" in docs/spec/blocks.md
     */
    private function addBlockStylesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('block_styles')
                    ->info('Styles added to any block type, shipped or not: block type code => style code => {label, class}')
                    ->useAttributeAsKey('block_type')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->useAttributeAsKey('code')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('label')->info('A translation key, shown in the builder\'s style picker')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('class')->info('The CSS class added to the block\'s root element')->isRequired()->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()->always(self::assertBlockStyleCodes(...))->end()
                ->end()
            ->end()
        ;
    }

    /**
     * What a project changes about a content type, shipped or not, by its code: the URL prefix, which moves every URL of
     * the content type without redirecting the old ones (see "URL shape" in docs/spec/urls-and-seo.md), and the default
     * layout, which overrides #[Layout(default: true)] (see "Configuration" in docs/spec/extending.md)
     */
    private function addContentTypesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('content_types')
                    ->info('What a project changes about a content type: content type code => {prefix, default_layout}')
                    ->useAttributeAsKey('code')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('prefix')
                                ->info('The URL prefix of a content type with its own URL, such as "pg". An empty prefix puts its URLs at the root')
                                ->validate()
                                    ->ifTrue(static fn (mixed $prefix): bool => !is_string($prefix) || ('' !== $prefix && !Slug::isValid($prefix)))
                                    ->then(static fn (mixed $prefix): never => throw new \InvalidArgumentException(sprintf(
                                        'The prefix %s must be empty or a string that doesn\'t start or end with a slash or contain two slashes in a row',
                                        is_string($prefix) ? sprintf('"%s"', $prefix) : get_debug_type($prefix),
                                    )))
                                ->end()
                            ->end()
                            ->scalarNode('default_layout')
                                ->info('A layout code, which overrides #[Layout(default: true)]')
                                ->cannotBeEmpty()
                                ->validate()->always(static fn (mixed $code): string => self::assertCode('layout', is_scalar($code) ? (string) $code : ''))->end()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()->always(self::assertContentTypeCodes(...))->end()
                ->end()
            ->end()
        ;
    }

    /**
     * The middle of the fallback chain, per locale: the locales a translatable field falls back through, in order, before
     * the store's fallback locale. See "Fallback chain" in docs/spec/localization.md
     */
    private function addFallbackLocalesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('fallback_locales')
                    ->info('The middle of the fallback chain, per locale: locale code => the locales it falls back through, in order, before the store\'s fallback locale')
                    ->useAttributeAsKey('locale')
                    ->arrayPrototype()
                        ->performNoDeepMerging()
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * How many revisions per entry setono:cms:revisions:prune keeps. See "Revisions" in docs/spec/publishing.md
     */
    private function addRevisionsSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('revisions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('keep')
                            ->info('The revisions kept per entry, newest first. setono:cms:revisions:prune deletes older ones')
                            ->min(1)
                            ->defaultValue(50)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * The media library's storage, the MIME types each kind of media item allows, the upload limit, and how long a
     * replaced file is kept. See "What the library holds", "Replacing a file" and "Image processing" in docs/spec/media.md
     */
    private function addMediaSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('media')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage')
                            ->info('A Flysystem storage service. The default writes to %kernel.project_dir%/public/media/cms, served at /media/cms. Another storage also needs Setono\\SyliusCMSPlugin\\Media\\MediaUrlGeneratorInterface aliased to a service that builds its URLs')
                            ->defaultValue(SetonoSyliusCMSExtension::STORAGE)
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('types')
                            ->info('The MIME types each kind of media item allows')
                            ->addDefaultsIfNotSet()
                            ->append(self::mimeTypes(MediaKind::Image, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml']))
                            ->append(self::mimeTypes(MediaKind::Video, ['video/mp4', 'video/webm']))
                            ->append(self::mimeTypes(MediaKind::Document, ['application/pdf']))
                            ->validate()->always(self::assertMimeTypesInOneKind(...))->end()
                        ->end()
                        ->scalarNode('max_upload_size')
                            ->info('In bytes, or as php.ini writes it, such as 20M. null means PHP\'s effective limit, and a value above it is lowered to it')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $size): bool => null !== $size)
                                ->then(static fn (mixed $size): int => FileSize::parse(is_int($size) || is_string($size) ? $size : ''))
                            ->end()
                        ->end()
                        ->scalarNode('grace_period')
                            ->info('An ISO 8601 duration before a replaced file and its variants are deleted, so pages cached before the replace don\'t show broken images')
                            ->defaultValue(GracePeriod::DEFAULT)
                            ->cannotBeEmpty()
                            ->validate()->always(self::assertDuration(...))->end()
                        ->end()
                        ->arrayNode('liip')
                            ->info('The names of the LiipImagine loader and resolver image variants are read and cached with. The plugin\'s own read from and cache into media.storage')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('loader')->defaultValue(SetonoSyliusCMSExtension::LIIP_NAME)->cannotBeEmpty()->end()
                                ->scalarNode('resolver')->defaultValue(SetonoSyliusCMSExtension::LIIP_NAME)->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('widths')
                            ->info('The widths in pixels cms_image() offers an image at, which a srcset takes what its layout needs of, capped at the source\'s width')
                            ->requiresAtLeastOneElement()
                            ->defaultValue([320, 640, 960, 1280, 1600, 1920, 2560])
                            ->scalarPrototype()
                                ->validate()->always(self::assertWidth(...))->end()
                            ->end()
                            ->validate()->always(self::ascendingWidths(...))->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * The window the default bestsellers calculator counts completed orders over. See "Bestsellers" in docs/spec/sources.md
     */
    private function addBestsellersSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('bestsellers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('window')
                            ->info('An ISO 8601 duration: the default bestsellers calculator counts the orders whose checkout completed this long ago or later')
                            ->defaultValue('P30D')
                            ->cannotBeEmpty()
                            ->validate()->always(self::assertDuration(...))->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * The scripts the builder loads as ES modules, which define the field controls of a project's own field types: the
     * custom elements named in #[FieldType(control:)]. See "Field controls" in docs/spec/extending.md
     */
    private function addBuilderSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('builder')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('scripts')
                            ->info('Field control scripts, as public paths, such as /build/app/cms/controls.js, or absolute URLs, loaded as ES modules in this order')
                            ->scalarPrototype()
                                ->validate()->always(self::assertScript(...))->end()
                            ->end()
                            ->validate()->always(self::eachOnce(...))->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param list<string> $scripts
     *
     * @return list<string> in their order, each once
     */
    private static function eachOnce(array $scripts): array
    {
        return array_values(array_unique($scripts));
    }

    /**
     * @throws \InvalidArgumentException when it's neither a public path nor an absolute http(s) URL
     */
    private static function assertScript(mixed $script): string
    {
        if (!is_string($script) || 1 !== preg_match('#^(/(?!/)|https?://)\S+$#', $script)) {
            throw new \InvalidArgumentException(sprintf('%s is neither a public path, such as /build/app/cms/controls.js, nor an absolute http(s) URL', is_scalar($script) ? sprintf('"%s"', (string) $script) : get_debug_type($script)));
        }

        return $script;
    }

    /**
     * @param list<int> $widths
     *
     * @return list<int> in ascending order, each once
     */
    private static function ascendingWidths(array $widths): array
    {
        $widths = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }

    /**
     * @throws \InvalidArgumentException when it isn't a positive number of pixels
     */
    private static function assertWidth(mixed $width): int
    {
        if (!is_int($width) || $width < 1) {
            throw new \InvalidArgumentException(sprintf('An image width must be a positive number of pixels, not %s', is_scalar($width) ? var_export($width, true) : get_debug_type($width)));
        }

        return $width;
    }

    /**
     * @throws \InvalidArgumentException when it isn't an ISO 8601 duration
     */
    private static function assertDuration(mixed $duration): string
    {
        $duration = is_scalar($duration) ? (string) $duration : get_debug_type($duration);
        Duration::fromIso8601($duration);

        return $duration;
    }

    /**
     * @param list<string> $default
     */
    private static function mimeTypes(MediaKind $kind, array $default): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($kind->value);
        $node
            ->info(sprintf('The MIME types a media item of kind %s may have', $kind->value))
            ->scalarPrototype()
                ->cannotBeEmpty()
                ->validate()
                    ->ifTrue(static fn (mixed $type): bool => !is_string($type) || 1 !== preg_match(self::MIME_TYPE, $type))
                    ->then(static fn (mixed $type): never => throw new \InvalidArgumentException(sprintf('"%s" isn\'t a MIME type, such as image/jpeg', is_scalar($type) ? (string) $type : get_debug_type($type))))
                ->end()
            ->end()
            ->defaultValue($default)
        ;

        return $node;
    }

    /**
     * @param array<string, list<string>> $types
     *
     * @return array<string, list<string>>
     */
    private static function assertMimeTypesInOneKind(array $types): array
    {
        $kinds = [];
        foreach ($types as $kind => $mimeTypes) {
            foreach ($mimeTypes as $mimeType) {
                if (isset($kinds[$mimeType]) && $kinds[$mimeType] !== $kind) {
                    throw new \InvalidArgumentException(sprintf('The MIME type "%s" is in both %s and %s, so an uploaded file of it would have no one kind', $mimeType, $kinds[$mimeType], $kind));
                }

                $kinds[$mimeType] = $kind;
            }
        }

        return $types;
    }

    /**
     * @param array<array-key, array<array-key, array{label: string, class: string}>> $blockStyles
     *
     * @return array<array-key, array<array-key, array{label: string, class: string}>>
     */
    private static function assertBlockStyleCodes(array $blockStyles): array
    {
        foreach ($blockStyles as $blockType => $styles) {
            self::assertCode('block type', (string) $blockType);
            foreach (array_keys($styles) as $style) {
                self::assertCode('block style', (string) $style);
            }
        }

        return $blockStyles;
    }

    /**
     * @param array<array-key, array{prefix?: string, default_layout?: string}> $contentTypes
     *
     * @return array<array-key, array{prefix?: string, default_layout?: string}>
     */
    private static function assertContentTypeCodes(array $contentTypes): array
    {
        foreach (array_keys($contentTypes) as $code) {
            self::assertCode('content type', (string) $code);
        }

        return $contentTypes;
    }

    private static function assertDisabledLayout(mixed $layout): string
    {
        $layout = is_scalar($layout) ? (string) $layout : '';
        $parts = explode('.', $layout);
        if (2 !== count($parts) || !Code::isValid($parts[0]) || !Code::isValid($parts[1])) {
            throw new \InvalidArgumentException(sprintf('A disabled layout must be "<content type code>.<layout code>", such as page.standard, not "%s"', $layout));
        }

        return $layout;
    }

    private static function assertCode(string $kind, string $code): string
    {
        if (!Code::isValid($code)) {
            throw new \InvalidArgumentException(sprintf('The %s code "%s" must match %s', $kind, $code, trim(Code::PATTERN, '/')));
        }

        return $code;
    }

    /**
     * The shipped Sylius resources. The draft and the rows beside an entry (its channels, its audience groups, who has
     * it open, its saves, its revisions, its old slugs, its scheduled publish, its share links and the channels a
     * page is the homepage of), and the bestseller scores, have no admin screens of their own, so they have no controller.
     * A project extends a shipped content type the way it extends any Sylius model:
     * by overriding classes.model, and translation.classes.model, with its own subclasses.
     * The channel and audience group rows always belong to an entry, and the homepage rows to a page, so their
     * factories create them for one. The share link factory gives each new share link its random token
     */
    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->append(self::entryResource())
                    ->append(self::resourceWithoutController('draft', Draft::class, DraftInterface::class, DraftRepository::class))
                    ->append(self::resourceWithoutController('entry_channel', EntryChannel::class, EntryChannelInterface::class, factory: EntryChannelFactory::class))
                    ->append(self::resourceWithoutController('entry_audience_group', EntryAudienceGroup::class, EntryAudienceGroupInterface::class, factory: EntryAudienceGroupFactory::class))
                    ->append(self::resourceWithoutController('entry_presence', EntryPresence::class, EntryPresenceInterface::class, EntryPresenceRepository::class))
                    ->append(self::resourceWithoutController('saved_change_set', SavedChangeSet::class, SavedChangeSetInterface::class, SavedChangeSetRepository::class))
                    ->append(self::resourceWithoutController('revision', Revision::class, RevisionInterface::class, RevisionRepository::class))
                    ->append(self::resourceWithoutController('old_slug', OldSlug::class, OldSlugInterface::class, OldSlugRepository::class))
                    ->append(self::resourceWithoutController('scheduled_publish', ScheduledPublish::class, ScheduledPublishInterface::class, ScheduledPublishRepository::class))
                    ->append(self::resourceWithoutController('share_link', ShareLink::class, ShareLinkInterface::class, ShareLinkRepository::class, ShareLinkFactory::class))
                    ->append(self::resourceWithoutController('homepage', Homepage::class, HomepageInterface::class, HomepageRepository::class, HomepageFactory::class))
                    ->append(self::mediaItemResource())
                    ->append(self::resourceWithoutController('media_folder', MediaFolder::class, MediaFolderInterface::class, MediaFolderRepository::class))
                    ->append(self::resourceWithoutController('media_usage', MediaUsage::class, MediaUsageInterface::class, MediaUsageRepository::class))
                    ->append(self::resourceWithoutController('bestseller_score', BestsellerScore::class, BestsellerScoreInterface::class))
                    ->children()
                        ->arrayNode('page')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Page::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(PageInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(TranslatableFactory::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                                ->arrayNode('translation')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->variableNode('options')->end()
                                        ->arrayNode('classes')
                                            ->addDefaultsIfNotSet()
                                            ->children()
                                                ->scalarNode('model')->defaultValue(PageTranslation::class)->cannotBeEmpty()->end()
                                                ->scalarNode('interface')->defaultValue(PageTranslationInterface::class)->cannotBeEmpty()->end()
                                                ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                                ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * The root of every content type, whose resource controller and grids give the admin its entry lists. It's the
     * abstract root of the entries' single table inheritance (ADR 0001), so it's never created through its factory
     */
    private static function entryResource(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('entry');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->variableNode('options')->end()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue(Entry::class)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue(EntryInterface::class)->cannotBeEmpty()->end()
                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                        ->scalarNode('repository')->defaultValue(EntryRepository::class)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * The media items, whose translations hold the alt text. The media library is a screen of its own, so they have no
     * controller
     */
    private static function mediaItemResource(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('media_item');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->variableNode('options')->end()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue(MediaItem::class)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue(MediaItemInterface::class)->cannotBeEmpty()->end()
                        ->scalarNode('repository')->defaultValue(MediaItemRepository::class)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue(TranslatableFactory::class)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('translation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('options')->end()
                        ->arrayNode('classes')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')->defaultValue(MediaItemTranslation::class)->cannotBeEmpty()->end()
                                ->scalarNode('interface')->defaultValue(MediaItemTranslationInterface::class)->cannotBeEmpty()->end()
                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    /**
     * @param class-string $model
     * @param class-string $interface
     * @param class-string $repository
     * @param class-string $factory
     */
    private static function resourceWithoutController(string $name, string $model, string $interface, string $repository = EntityRepository::class, string $factory = Factory::class): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->variableNode('options')->end()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue($model)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue($interface)->cannotBeEmpty()->end()
                        ->scalarNode('repository')->defaultValue($repository)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue($factory)->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
