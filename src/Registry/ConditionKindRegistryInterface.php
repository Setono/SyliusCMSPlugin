<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

interface ConditionKindRegistryInterface
{
    public function has(string $code): bool;

    /**
     * @throws UnknownConditionKindException
     */
    public function get(string $code): ConditionKindMetadata;

    /**
     * @return list<ConditionKindMetadata>
     */
    public function all(): array;
}
