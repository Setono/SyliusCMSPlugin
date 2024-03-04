<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This compiler pass will tag the \Setono\SyliusCMSPlugin\EventListener\ElementCacheInvalidatorListener with all the
 * relevant tags and events
 */
final class TagElementCacheInvalidatorListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('setono_sylius_cms.event_listener.element_cache_invalidator')) {
            return;
        }

        $elementCacheInvalidator = $container->getDefinition('setono_sylius_cms.event_listener.element_cache_invalidator');

        $aliasesToTag = [];

        /** @var array<string, array> $resources */
        $resources = $container->getParameter('sylius.resources');
        foreach ($resources as $alias => $resource) {
            if (!isset($resource['classes']['model']) || !is_string($resource['classes']['model'])) {
                continue;
            }

            if (!is_a($resource['classes']['model'], ElementInterface::class, true)) {
                continue;
            }

            $aliasesToTag[] = $alias;
        }

        foreach ($aliasesToTag as $alias) {
            $elementCacheInvalidator->addTag('kernel.event_listener', [
                'event' => sprintf('%s.post_update', $alias),
                'method' => 'postUpdate',
            ])->addTag('kernel.event_listener', [
                'event' => sprintf('%s.post_delete', $alias),
                'method' => 'postRemove',
            ]);
        }
    }
}
