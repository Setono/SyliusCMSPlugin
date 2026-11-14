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
    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

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
                    ->validate()
                        ->always(static function (array $blockStyles): array {
                            foreach ($blockStyles as $blockType => $styles) {
                                self::assertCode('block type', (string) $blockType);
                                foreach (array_keys($styles) as $style) {
                                    self::assertCode('block style', (string) $style);
                                }
                            }

                            return $blockStyles;
                        })
                    ->end()
                ->end()
            ->end()
        ;
    }

    private static function assertCode(string $kind, string $code): void
    {
        if (1 !== preg_match(self::CODE_PATTERN, $code)) {
            throw new \InvalidArgumentException(sprintf('The %s code "%s" must match %s', $kind, $code, trim(self::CODE_PATTERN, '/')));
        }
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
