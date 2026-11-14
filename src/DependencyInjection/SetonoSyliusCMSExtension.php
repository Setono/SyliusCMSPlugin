<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterBlockTypesPass;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusCMSExtension extends Extension implements PrependExtensionInterface
{
    public const FIELD_HANDLER_TAG = 'setono_sylius_cms.field_handler';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->processConfiguration(new Configuration(), $configs);

        // The bundle's Doctrine mapping of the Model namespace is registered when this parameter exists
        $container->setParameter(sprintf('setono_sylius_cms.driver.%s', SyliusResourceBundle::DRIVER_DOCTRINE_ORM), true);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $container->registerForAutoconfiguration(FieldHandlerInterface::class)->addTag(self::FIELD_HANDLER_TAG);
        $container->registerAttributeForAutoconfiguration(BlockType::class, static function (ChildDefinition $definition): void {
            $definition->addTag(RegisterBlockTypesPass::TAG);
        });
    }

    /**
     * The shipped content types are Sylius resources. They're registered under sylius_resource.resources,
     * where a project overrides classes.model and translation.classes.model to extend them
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sylius_resource', [
            'resources' => [
                'setono_sylius_cms.page' => [
                    'driver' => SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
                    'classes' => [
                        'model' => Page::class,
                        'interface' => PageInterface::class,
                    ],
                    'translation' => [
                        'classes' => [
                            'model' => PageTranslation::class,
                            'interface' => PageTranslationInterface::class,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
