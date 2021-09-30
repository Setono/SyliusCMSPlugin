<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use DateTimeInterface;

interface EnabledDateIntervalAwareInterface
{
    /**
     * The entity is enabled from this date
     */
    public function getEnabledFrom(): ?DateTimeInterface;

    public function setEnabledFrom(?DateTimeInterface $enabledFrom): void;

    /**
     * The entity is enabled until this date
     */
    public function getEnabledUntil(): ?DateTimeInterface;

    public function setEnabledUntil(?DateTimeInterface $enabledUntil): void;

    public function isEnabledOn(DateTimeInterface $dateTime): bool;
}
