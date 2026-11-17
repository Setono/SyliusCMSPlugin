<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Registers a context resolver for one context key: a service implementing
 * Setono\SyliusCMSPlugin\Context\ContextResolverInterface. One a project registers for a shipped key replaces the
 * shipped one. See "Context keys and resolvers" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsContextResolver
{
    /**
     * @param string $key the context key it resolves, which setono_sylius_cms.context_keys declares
     */
    public function __construct(public string $key)
    {
    }
}
