<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TranslatableInterface;

interface BlockInterface extends ElementInterface, TranslatableInterface
{
    public function getDefaultContent(): ?string;

    public function setDefaultContent(?string $defaultContent): void;

    public function getContent(): ?string;

    public function setContent(?string $content): void;
}
