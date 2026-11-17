<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Registry\Code;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The classes of the services carrying a tag: the block types, layouts, upgraders, link targets and sources a project
 * registers by attribute, which autoconfiguration tags
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

    /**
     * The services carrying a tag that registers them under the code of their attribute, such as link targets and
     * sources, by their tag's priority: service id, class and attribute by code. Each must have the attribute, a valid
     * code that no other has, and implement the interface
     *
     * @template T of AsLinkTarget|AsSource
     *
     * @param string $what what the services are, for the errors, such as "source"
     * @param string $attribute the attribute's name, for the errors, such as "#[AsSource]"
     * @param \Closure(class-string): ?T $attributeOf the class's attribute, or null when it has none
     * @param class-string $interface
     *
     * @return array<string, array{string, class-string, T}> code => [service id, class, attribute]
     */
    public static function byCode(ContainerBuilder $container, string $tag, string $what, string $attribute, \Closure $attributeOf, string $interface): array
    {
        $byCode = [];
        foreach (self::byPriority($container, $tag, $what) as $id => $class) {
            $attributeOfClass = $attributeOf($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no %s', $id, $tag, $class, $attribute));
            $code = $attributeOfClass->code;

            if (!Code::isValid($code)) {
                throw new \LogicException(sprintf('The %s code "%s" of %s must match %s', $what, $code, $class, Code::PATTERN));
            }

            if (!is_a($class, $interface, true)) {
                throw new \LogicException(sprintf('The %s %s must implement %s', $what, $class, $interface));
            }

            if (isset($byCode[$code]) && $byCode[$code][1] !== $class) {
                throw new \LogicException(sprintf('The %s code "%s" is used by both %s and %s. Codes are unique per kind, so give one of them another code', $what, $code, $byCode[$code][1], $class));
            }

            $byCode[$code] = [$id, $class, $attributeOfClass];
        }

        return $byCode;
    }
}
