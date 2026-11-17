<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The classes of the services carrying a tag: the block types, layouts and upgraders a project registers by attribute,
 * which autoconfiguration tags
 *
 * @internal
 */
final class TaggedClasses
{
    /**
     * @param string $what what the services are, for the error, such as "block type"
     *
     * @return array<string, class-string> service id => class
     */
    public static function of(ContainerBuilder $container, string $tag, string $what): array
    {
        $classes = [];
        foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class) : null;
            if (null === $reflection) {
                throw new \LogicException(sprintf('The %s service "%s" must have an existing class', $what, $id));
            }

            $classes[$id] = $reflection->getName();
        }

        return $classes;
    }

    /**
     * The classes of the services carrying a tag, by the priority of their tag, highest first, and else in the order
     * they're defined in. A bundle's services are defined after an app's, so the shipped ones give themselves a
     * priority to come first
     *
     * @param string $what what the services are, for the error, such as "source"
     *
     * @return array<string, class-string> service id => class
     */
    public static function byPriority(ContainerBuilder $container, string $tag, string $what): array
    {
        $classes = self::of($container, $tag, $what);

        $priorities = [];
        foreach ($container->findTaggedServiceIds($tag) as $id => $tags) {
            $first = $tags[0] ?? null;
            $priority = is_array($first) ? $first['priority'] ?? 0 : 0;
            $priorities[$id] = is_numeric($priority) ? (int) $priority : 0;
        }

        $ids = array_keys($classes);
        $order = array_flip($ids);
        usort($ids, static fn (string $a, string $b): int => [$priorities[$b] ?? 0, $order[$a]] <=> [$priorities[$a] ?? 0, $order[$b]]);

        return array_combine($ids, array_map(static fn (string $id): string => $classes[$id], $ids));
    }
}
