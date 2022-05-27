<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use function array_keys;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterElementLoadersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('setono_sylius_cms.twig.loader.composite_element')) {
            return;
        }

        $definition = $container->getDefinition('setono_sylius_cms.twig.loader.composite_element');

        /** @var string $id */
        foreach (array_keys($container->findTaggedServiceIds('setono_sylius_cms.element_loader')) as $id) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }
    }
}
