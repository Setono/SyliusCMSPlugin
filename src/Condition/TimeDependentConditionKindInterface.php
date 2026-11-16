<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

/**
 * A condition kind that reads the time, which reports when its result can next change, so a later cache can expire
 * output that read it then. See "Caching contract" in docs/spec/targeting.md
 */
interface TimeDependentConditionKindInterface extends ConditionKindInterface
{
    /**
     * The next time the result can change after $now, or null when it never changes again
     */
    public function nextChange(object $params, \DateTimeImmutable $now): ?\DateTimeImmutable;
}
