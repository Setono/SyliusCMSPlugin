<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

interface InternalDescriptionAwareInterface
{
    public function getInternalDescription(): ?string;

    public function setInternalDescription(?string $internalDescription): void;
}
