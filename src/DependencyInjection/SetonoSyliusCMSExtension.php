<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterLayoutsPass;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterUpgradersPass;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
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

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{disabled: array{block_types: list<string>}, resources: array<string, array<string, mixed>>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('setono_sylius_cms.disabled.block_types', $config['disabled']['block_types']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // Registers the shipped resources, and turns on the Doctrine mapping of the Model namespace
        $this->registerResources('setono_sylius_cms', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

        $container->registerForAutoconfiguration(FieldHandlerInterface::class)->addTag(self::FIELD_HANDLER_TAG);
        $container->registerAttributeForAutoconfiguration(BlockType::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterBlockTypesPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(Layout::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterLayoutsPass::TAG);
        });
        $container->registerAttributeForAutoconfiguration(AsUpgrader::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterUpgradersPass::TAG);
        });
    }

    /**
     * Loads the shipped stylesheet on every shop page, at priority 20: above Sylius's own styles (10),
     * so it declares the cms cascade layer before the theme's CSS. See "Loading the stylesheet and script" in docs/spec/blocks.md
     */
    public function prepend(ContainerBuilder $container): void
    {
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
}
