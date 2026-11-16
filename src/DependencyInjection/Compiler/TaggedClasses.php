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
}
