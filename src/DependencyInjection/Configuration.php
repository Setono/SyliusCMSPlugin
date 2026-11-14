<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
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

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    /**
     * The shipped Sylius resources. The draft has no admin screens of its own, so it has no controller.
     * A project extends a shipped content type the way it extends any Sylius model:
     * by overriding classes.model, and translation.classes.model, with its own subclasses
     */
    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('draft')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Draft::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(DraftInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(DraftRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
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
}
