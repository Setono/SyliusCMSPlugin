<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Every post-commit event that changes what shoppers see implements this, so cache invalidation can listen once.
 * See "Events" in docs/spec/extending.md
 */
interface StorefrontChangedInterface
{
    /**
     * @return list<string>|null the UUIDs of the records the change touched. null means it can't be narrowed to records,
     *                           and an empty list means it touched none
     */
    public function getAffectedUuids(): ?array;
}
