<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactory;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactory;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\EntryPresenceInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;
use Setono\SyliusCMSPlugin\Model\SavedChangeSetInterface;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepository;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepository;
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

    private static function assertCode(string $kind, string $code): void
    {
        if (!Code::isValid($code)) {
            throw new \InvalidArgumentException(sprintf('The %s code "%s" must match %s', $kind, $code, trim(Code::PATTERN, '/')));
        }
    }

    /**
     * The shipped Sylius resources. The draft and the rows beside an entry (its channels, its audience groups, who has
     * it open and its saves) have no admin screens of their own, so they have no controller.
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
                    ->append(self::resourceWithoutController('draft', Draft::class, DraftInterface::class, DraftRepository::class))
                    ->append(self::resourceWithoutController('entry_channel', EntryChannel::class, EntryChannelInterface::class, factory: EntryChannelFactory::class))
                    ->append(self::resourceWithoutController('entry_audience_group', EntryAudienceGroup::class, EntryAudienceGroupInterface::class, factory: EntryAudienceGroupFactory::class))
                    ->append(self::resourceWithoutController('entry_presence', EntryPresence::class, EntryPresenceInterface::class, EntryPresenceRepository::class))
                    ->append(self::resourceWithoutController('saved_change_set', SavedChangeSet::class, SavedChangeSetInterface::class, SavedChangeSetRepository::class))
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
