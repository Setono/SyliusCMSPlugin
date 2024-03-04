<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Symfony\Component\String\u;

final class AddResourceDeleteRoutesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('setono_sylius_cms.event_subscriber.resource_delete')) {
            return;
        }

        $resourceDeleteSubscriber = $container->getDefinition('setono_sylius_cms.event_subscriber.resource_delete');

        /** @var array<string, array{classes: array{model: class-string}}> $resources */
        $resources = $container->getParameter('sylius.resources');
        foreach ($resources as $alias => $resource) {
            if (!is_a($resource['classes']['model'], ElementInterface::class, true)) {
                continue;
            }

            $prefix = sprintf(
                '%s_admin_%s',
                u($alias)->beforeLast('.')->toString(),
                u($alias)->afterLast('.')->toString(),
            );

            $resourceDeleteSubscriber->addMethodCall('addRoute', [sprintf('%s_delete', $prefix)]);
            $resourceDeleteSubscriber->addMethodCall('addRoute', [sprintf('%s_bulk_delete', $prefix)]);
        }
    }
}
