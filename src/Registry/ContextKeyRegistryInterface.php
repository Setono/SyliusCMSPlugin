<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * The context keys the code declares under setono_sylius_cms.context_keys: product, taxon and entry first, then a
 * project's. See "Context keys" in docs/spec/slots.md
 */
interface ContextKeyRegistryInterface
{
    public function has(string $key): bool;

    /**
     * @throws UnknownContextKeyException
     */
    public function get(string $key): ContextKeyMetadata;

    /**
     * Every context key, in the order the config declares them
     *
     * @return list<ContextKeyMetadata>
     */
    public function all(): array;
}
