<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SetonoSyliusCMSExtension extends AbstractResourceExtension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{cache: array{enabled: bool, ttl: int}, driver: string, resources: array<string, mixed>, templates: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $container->setParameter('setono_sylius_cms.templates', $config['templates']);

        $this->registerResources('setono_sylius_cms', $config['driver'], $config['resources'], $container);

        $loader->load('services.xml');

        if ($config['cache']['enabled']) {
            $container->setParameter('setono_sylius_cms.cache.ttl', $config['cache']['ttl']);
            $loader->load('services/conditional/cache.xml');
        }
    }
}
