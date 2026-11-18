<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Symfony\Component\Translation\TranslatableMessage;

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
     * How a message names the context key: its label, or the key itself when the code no longer declares it, such as
     * one a snippet still declares after a deploy removed it
     */
    public function labelOf(string $key): TranslatableMessage|string;

    /**
     * Every context key, in the order the config declares them
     *
     * @return list<ContextKeyMetadata>
     */
    public function all(): array;
}
