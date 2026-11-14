<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusCMSExtension extends AbstractResourceExtension
{
    public const FIELD_HANDLER_TAG = 'setono_sylius_cms.field_handler';

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{resources: array<string, array<string, mixed>>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // Registers the shipped resources, and turns on the Doctrine mapping of the Model namespace
        $this->registerResources('setono_sylius_cms', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

        $container->registerForAutoconfiguration(FieldHandlerInterface::class)->addTag(self::FIELD_HANDLER_TAG);
        $container->registerAttributeForAutoconfiguration(BlockType::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterBlockTypesPass::TAG);
        });
    }
}
