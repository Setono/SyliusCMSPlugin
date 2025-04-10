<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Form\Type\AssetType;
use Setono\SyliusCMSPlugin\Form\Type\BlockTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\BlockType;
use Setono\SyliusCMSPlugin\Form\Type\CarouselType;
use Setono\SyliusCMSPlugin\Form\Type\PageTranslationType;
use Setono\SyliusCMSPlugin\Form\Type\PageType;
use Setono\SyliusCMSPlugin\Form\Type\SlideType;
use Setono\SyliusCMSPlugin\Form\Type\TemplateType;
use Setono\SyliusCMSPlugin\Model\Asset;
use Setono\SyliusCMSPlugin\Model\Block;
use Setono\SyliusCMSPlugin\Model\BlockTranslation;
use Setono\SyliusCMSPlugin\Model\Carousel;
use Setono\SyliusCMSPlugin\Model\EmbeddedVideoSlide;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Slide;
use Setono\SyliusCMSPlugin\Model\Template;
use Setono\SyliusCMSPlugin\Repository\AssetRepository;
use Setono\SyliusCMSPlugin\Repository\BlockRepository;
use Setono\SyliusCMSPlugin\Repository\CarouselRepository;
use Setono\SyliusCMSPlugin\Repository\PageRepository;
use Setono\SyliusCMSPlugin\Repository\TemplateRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ResourceBundle\Form\Type\DefaultResourceType;
use Sylius\Component\Resource\Factory\Factory;
use Sylius\Component\Resource\Factory\TranslatableFactory;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_cms');
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress MixedMethodCall,PossiblyUndefinedMethod,PossiblyNullReference,UndefinedInterfaceMethod */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('routing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pages_path_prefix')
                            ->defaultValue('pages')
                            ->example('pg')
                            ->info('This is the prefix for the pages path, i.e. /pages/my-page')
                        ->end()
                    ->end()
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

        $resources = $rootNode->children()->arrayNode('resources')->addDefaultsIfNotSet()->children();

        self::addResource($resources, 'asset', Asset::class, AssetRepository::class, AssetType::class);
        self::addResource($resources, 'block', Block::class, BlockRepository::class, BlockType::class, BlockTranslation::class, BlockTranslationType::class);
        self::addResource($resources, 'carousel', Carousel::class, CarouselRepository::class, CarouselType::class);
        self::addResource($resources, 'embedded_video_slide', EmbeddedVideoSlide::class);
        self::addResource($resources, 'page', Page::class, PageRepository::class, PageType::class, PageTranslation::class, PageTranslationType::class);
        self::addResource($resources, 'slide', Slide::class, null, SlideType::class);
        self::addResource($resources, 'template', Template::class, TemplateRepository::class, TemplateType::class);

        return $treeBuilder;
    }

    private static function addResource(
        NodeBuilder $nodeBuilder,
        string $name,
        string $class,
        string $repository = null,
        string $form = null,
        string $translationClass = null,
        string $translationForm = null,
    ): void {
        /** @psalm-suppress MixedMethodCall,PossiblyUndefinedMethod,PossiblyNullReference,UndefinedInterfaceMethod,MixedAssignment */
        $node = $nodeBuilder
            ->arrayNode($name)
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue($class)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->defaultValue($repository ?? EntityRepository::class)->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue($form ?? DefaultResourceType::class)->cannotBeEmpty()->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
                        ->end()
                    ->end()
        ;

        if (null !== $translationClass) {
            /** @psalm-suppress MixedMethodCall,PossiblyUndefinedMethod,PossiblyNullReference,UndefinedInterfaceMethod */
            $node->arrayNode('translation')
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue($translationClass)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->cannotBeEmpty()->end()
                            ->scalarNode('form')->defaultValue($translationForm ?? DefaultResourceType::class)->cannotBeEmpty()->end()
                            ->scalarNode('factory')->defaultValue(TranslatableFactory::class)->end()
            ;
        }
    }
}
