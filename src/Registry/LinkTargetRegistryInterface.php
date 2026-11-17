<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

interface LinkTargetRegistryInterface
{
    public function has(string $code): bool;

    /**
     * @throws UnknownLinkTargetException
     */
    public function get(string $code): LinkTargetMetadata;

    /**
     * @return list<LinkTargetMetadata> in the order they were registered
     */
    public function all(): array;
}
