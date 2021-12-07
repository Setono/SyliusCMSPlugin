<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

trait InternalDescriptionAwareTrait
{
    protected ?string $internalDescription = null;

    public function getInternalDescription(): ?string
    {
        return $this->internalDescription;
    }

    public function setInternalDescription(?string $internalDescription): void
    {
        $this->internalDescription = $internalDescription;
    }
}
