<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Model\SlideInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Webmozart\Assert\Assert;

/**
 * This will make sure Doctrine can resolve child interfaces of the SlideInterface to concrete entities
 */
final class ResolveTargetEntitiesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('sylius.resources') || !$container->hasDefinition('doctrine.orm.listeners.resolve_target_entity')) {
            return;
        }

        /** @var array<string, array{classes: array{model: class-string}}> $resources */
        $resources = $container->getParameter('sylius.resources');

        /** @var array<class-string, class-string> $targets */
        $targets = [];

        foreach ($resources as $resource) {
            ['model' => $model] = $resource['classes'];

            if (!is_a($model, SlideInterface::class, true)) {
                continue;
            }

            $interfaces = class_implements($model);
            if (false !== ($parent = get_parent_class($model))) {
                $interfaces = array_diff($interfaces, class_implements($parent));
            }

            $interfaces = array_values(array_filter($interfaces, static function (string $interface): bool {
                return is_a($interface, SlideInterface::class, true);
            }));

            Assert::count($interfaces, 1);

            $targets[$interfaces[0]] = $model;
        }

        $resolver = $container->getDefinition('doctrine.orm.listeners.resolve_target_entity');

        foreach ($targets as $interface => $target) {
            $resolver->addMethodCall('addResolveTargetEntity', [$interface, $target, []]);
        }
    }
}
