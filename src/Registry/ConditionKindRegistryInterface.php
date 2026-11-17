<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

interface ConditionKindRegistryInterface
{
    public function has(string $code): bool;

    /**
     * The kind with the code, or null for a code the code doesn't have, such as a stored condition's whose kind a deploy
     * removed
     */
    public function find(string $code): ?ConditionKindMetadata;

    /**
     * @throws UnknownConditionKindException
     */
    public function get(string $code): ConditionKindMetadata;

    /**
     * @return list<ConditionKindMetadata>
     */
    public function all(): array;
}
