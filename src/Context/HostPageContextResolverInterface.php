<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

/**
 * Resolves the host-page context a slot provides: each key from what the slot is passed, or else through the key's
 * context resolver. See "Context resolvers" in docs/spec/slots.md and "Rendering a slot" in docs/spec/rendering.md
 */
interface HostPageContextResolverInterface
{
    /**
     * @param list<string> $keys the context keys to resolve, such as those a slot provides
     * @param array<array-key, mixed> $passed what the slot is passed, such as its template event's context
     *
     * @return array<string, object>|null the object of each key, or null when one can't be resolved
     */
    public function resolve(array $keys, array $passed): ?array;
}
