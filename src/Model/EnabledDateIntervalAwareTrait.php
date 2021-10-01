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

    public function isEnabledOn(DateTimeInterface $dateTime): bool
    {
        if (null !== $this->enabledUntil && $this->enabledUntil < $dateTime) {
            return false;
        }

        if (null !== $this->enabledFrom && $this->enabledFrom > $dateTime) {
            return false;
        }

        return true;
    }
}
