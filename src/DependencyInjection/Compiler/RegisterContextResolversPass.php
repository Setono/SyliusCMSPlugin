<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Setono\SyliusCMSPlugin\Context\HostPageContextResolver;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the services carrying #[AsContextResolver], which autoconfiguration tags, to the host-page context, by the
 * context key each resolves. For a key with more than one, the one whose tag has the highest priority wins: the
 * shipped ones give theirs a low one, so a project's replaces them. Two at the same priority fail the container build,
 * and so does a resolver of a key setono_sylius_cms.context_keys doesn't declare, or one that doesn't implement
 * ContextResolverInterface. See "Context keys and resolvers" in docs/spec/extending.md
 */
final class RegisterContextResolversPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.context_resolver';

    private const CONTEXT_KEYS_PARAMETER = 'setono_sylius_cms.context_keys';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(HostPageContextResolver::class)) {
            return;
        }

        $contextKeys = $container->hasParameter(self::CONTEXT_KEYS_PARAMETER) ? $container->getParameter(self::CONTEXT_KEYS_PARAMETER) : [];
        $contextKeys = is_array($contextKeys) ? $contextKeys : [];

        $priorities = self::priorities($container);

        /** @var array<string, array{string, class-string, int}> $byKey key => [service id, class, priority] */
        $byKey = [];
        foreach (TaggedClasses::byPriority($container, self::TAG, 'context resolver') as $id => $class) {
            $attribute = AttributeReader::contextResolver($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[AsContextResolver]', $id, self::TAG, $class));
            $key = $attribute->key;

            if (!is_a($class, ContextResolverInterface::class, true)) {
                throw new \LogicException(sprintf('The context resolver %s must implement %s', $class, ContextResolverInterface::class));
            }

            if (!isset($contextKeys[$key])) {
                throw new \LogicException(sprintf('The context resolver %s resolves the context key "%s", which isn\'t declared under %s', $class, $key, self::CONTEXT_KEYS_PARAMETER));
            }

            $priority = $priorities[$id] ?? 0;
            if (isset($byKey[$key])) {
                // By priority, highest first, so the one already there wins, unless it's as high
                if ($byKey[$key][2] === $priority && $byKey[$key][1] !== $class) {
                    throw new \LogicException(sprintf('Both %s and %s resolve the context key "%s". Give the one that replaces the other a higher priority', $byKey[$key][1], $class, $key));
                }

                continue;
            }

            $byKey[$key] = [$id, $class, $priority];
        }

        $container->getDefinition(HostPageContextResolver::class)->replaceArgument(1, ServiceLocatorTagPass::register(
            $container,
            array_map(static fn (array $resolver): Reference => new Reference($resolver[0]), $byKey),
        ));
    }

    /**
     * @return array<string, int> service id => the priority of its tag
     */
    private static function priorities(ContainerBuilder $container): array
    {
        $priorities = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $first = $tags[0] ?? null;
            $priority = is_array($first) ? $first['priority'] ?? 0 : 0;
            $priorities[$id] = is_numeric($priority) ? (int) $priority : 0;
        }

        return $priorities;
    }
}
