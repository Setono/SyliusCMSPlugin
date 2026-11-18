<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Context\HostPageContextResolverInterface;

/**
 * Records the context the slot rendering now resolved, and for each key whether it was passed, such as by the
 * template event, or found by the key's context resolver. Or the key it couldn't resolve. Registered in debug only
 *
 * @internal
 */
final readonly class TraceableHostPageContextResolver implements HostPageContextResolverInterface
{
    public function __construct(
        private HostPageContextResolverInterface $resolver,
        private RenderedSlots $slots,
    ) {
    }

    public function resolve(array $keys, array $passed): ?array
    {
        $context = $this->resolver->resolve($keys, $passed);

        $slot = $this->slots->current();
        if (null === $slot) {
            return $context;
        }

        if (null === $context) {
            $slot->unresolvedKey = $this->firstUnresolved($keys, $passed);

            return null;
        }

        $slot->context = [];
        foreach ($context as $key => $object) {
            $slot->context[$key] = ['object' => $object, 'passed' => ($passed[$key] ?? null) === $object];
        }

        return $context;
    }

    /**
     * The resolver resolves the keys in order and stops at the first it can't, having asked that key's context
     * resolver, and it reuses what each context resolver found until the next main request. So asking it again for
     * each key up to that one runs no context resolver
     *
     * @param list<string> $keys
     * @param array<array-key, mixed> $passed
     */
    private function firstUnresolved(array $keys, array $passed): ?string
    {
        foreach ($keys as $key) {
            if (null === $this->resolver->resolve([$key], $passed)) {
                return $key;
            }
        }

        return null;
    }
}
