<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use DateTimeInterface;

trait EnabledDateIntervalAwareTrait
{
    protected ?DateTimeInterface $enabledFrom = null;

    protected ?DateTimeInterface $enabledUntil = null;

    public function getEnabledFrom(): ?DateTimeInterface
    {
        return $this->enabledFrom;
    }

    public function setEnabledFrom(?DateTimeInterface $enabledFrom): void
    {
        $this->enabledFrom = $enabledFrom;
    }

    public function getEnabledUntil(): ?DateTimeInterface
    {
        return $this->enabledUntil;
    }

    public function setEnabledUntil(?DateTimeInterface $enabledUntil): void
    {
        $this->enabledUntil = $enabledUntil;
    }

    // todo I am pretty sure this can be simplified
    public function isEnabledOn(DateTimeInterface $dateTime): bool
    {
        if (null === $this->enabledFrom && null === $this->enabledUntil) {
            return true;
        }

        if (null === $this->enabledFrom && $dateTime <= $this->enabledUntil) {
            return true;
        }

        if (null === $this->enabledUntil && $dateTime >= $this->enabledFrom) {
            return true;
        }

        if ($dateTime >= $this->enabledFrom && $dateTime <= $this->enabledUntil) {
            return true;
        }

        return false;
    }
}
