<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

interface BlockTranslationInterface extends IdAwareInterface, ResourceInterface, TranslationInterface
{
    public function getContent(): ?string;

    public function setContent(?string $content): void;

    public function getRawContent(): ?string;

    public function setRawContent(?string $rawContent): void;
}
