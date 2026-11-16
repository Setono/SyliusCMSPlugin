<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
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
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepository;
use Setono\SyliusCMSPlugin\Repository\EntryRepository;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepository;
use Setono\SyliusCMSPlugin\Repository\RevisionRepository;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepository;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Factory\TranslatableFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
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
                    ->end()
                ->end()
                ->booleanNode('assets')
                    ->info('false stops loading the shipped stylesheet, for projects that bundle it into their own build')
                    ->defaultTrue()
                ->end()
            ->end()
        ;

        $this->addBlockStylesSection($rootNode);
        $this->addContentTypesSection($rootNode);
        $this->addFallbackLocalesSection($rootNode);
        $this->addRevisionsSection($rootNode);
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
     * the content type without redirecting the old ones. See "URL shape" in docs/spec/urls-and-seo.md
     */
    private function addContentTypesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('content_types')
                    ->info('What a project changes about a content type: content type code => {prefix}')
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
     * @param array<array-key, array{prefix?: string}> $contentTypes
     *
     * @return array<array-key, array{prefix?: string}>
     */
    private static function assertContentTypeCodes(array $contentTypes): array
    {
        foreach (array_keys($contentTypes) as $code) {
            self::assertCode('content type', (string) $code);
        }

        return $contentTypes;
    }

    private static function assertCode(string $kind, string $code): void
    {
        if (!Code::isValid($code)) {
            throw new \InvalidArgumentException(sprintf('The %s code "%s" must match %s', $kind, $code, trim(Code::PATTERN, '/')));
        }
    }

    /**
     * The shipped Sylius resources. The draft and the rows beside an entry (its channels, its audience groups, who has
     * it open, its saves, its revisions and its old slugs) have no admin screens of their own, so they have no controller.
     * A project extends a shipped content type the way it extends any Sylius model:
     * by overriding classes.model, and translation.classes.model, with its own subclasses.
     * The channel and audience group rows always belong to an entry, so their factories create them for one
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
