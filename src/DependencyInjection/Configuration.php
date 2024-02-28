<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Doctrine\ORM\AssetRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\BlockRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\CarouselRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\PageRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\TemplateRepository;
use Setono\SyliusCMSPlugin\Doctrine\ORM\ViewRepository;
use Setono\SyliusCMSPlugin\Form\Type\AssetType;
use Setono\SyliusCMSPlugin\Form\Type\BlockTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\BlockType;
use Setono\SyliusCMSPlugin\Form\Type\CarouselBlockType;
use Setono\SyliusCMSPlugin\Form\Type\CarouselType;
use Setono\SyliusCMSPlugin\Form\Type\PageTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\PageType;
use Setono\SyliusCMSPlugin\Form\Type\TemplateType;
use Setono\SyliusCMSPlugin\Form\Type\ViewType;
use Setono\SyliusCMSPlugin\Model\Asset;
use Setono\SyliusCMSPlugin\Model\Block;
use Setono\SyliusCMSPlugin\Model\BlockTranslation;
use Setono\SyliusCMSPlugin\Model\Carousel;
use Setono\SyliusCMSPlugin\Model\CarouselBlock;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Template;
use Setono\SyliusCMSPlugin\Model\View;
use Setono\SyliusCMSPlugin\Model\ViewBlock;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Form\Type\DefaultResourceType;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Resource\Factory\Factory;
use Sylius\Component\Resource\Factory\TranslatableFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_cms');
        $rootNode = $treeBuilder->getRootNode();

        /**
         * @psalm-suppress MixedMethodCall,PossiblyUndefinedMethod,PossiblyNullReference,UndefinedInterfaceMethod
         */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('driver')
                    ->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)
                ->end()
                ->arrayNode('templates')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('code')
                                ->isRequired()
                            ->end()
                            ->scalarNode('label')->end()
                            ->scalarNode('description')->end()
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        /**
         * @psalm-suppress MixedMethodCall,PossiblyUndefinedMethod,PossiblyNullReference,UndefinedInterfaceMethod
         */
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('asset')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Asset::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(AssetRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(AssetType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('block')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Block::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(BlockRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(BlockType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                                ->arrayNode('translation')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->variableNode('options')->end()
                                        ->arrayNode('classes')
                                            ->addDefaultsIfNotSet()
                                            ->children()
                                                ->scalarNode('model')->defaultValue(BlockTranslation::class)->cannotBeEmpty()->end()
                                                ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                                ->scalarNode('form')->defaultValue(BlockTranslationType::class)->cannotBeEmpty()->end()
                                                ->scalarNode('factory')->defaultValue(TranslatableFactory::class)->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('carousel')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Carousel::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(CarouselRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(CarouselType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('carousel_block')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(CarouselBlock::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(CarouselBlockType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
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
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(PageRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(PageType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
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
                                                ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                                ->scalarNode('form')->defaultValue(PageTranslationType::class)->cannotBeEmpty()->end()
                                                ->scalarNode('factory')->defaultValue(TranslatableFactory::class)->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('template')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Template::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(TemplateRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(TemplateType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('view')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(View::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(ViewRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(ViewType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('view_block')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(ViewBlock::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->cannotBeEmpty()->end()
                                        ->scalarNode('form')->defaultValue(DefaultResourceType::class)->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
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
