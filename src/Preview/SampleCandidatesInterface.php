<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Where a snippet's preview looks for the sample object it starts with: objects of a context key that are likely to
 * match a page scope, best first
 *
 * @internal
 */
interface SampleCandidatesInterface
{
    /**
     * @param string $key a context key whose objects can be sample objects (SampleObjects::KEYS)
     * @param list<array<string, mixed>> $pageScope the placement's page scope, in the stored condition format
     *
     * @return iterable<string> the refs of the candidates, best first: codes, or UUIDs for entries
     */
    public function refs(string $key, array $pageScope, RenderContext $context): iterable;
}
