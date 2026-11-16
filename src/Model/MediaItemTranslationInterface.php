<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * A media item's metadata in one locale: its alt text
 */
interface MediaItemTranslationInterface extends ResourceInterface, TranslationInterface
{
    public function getId(): ?int;

    public function getAltText(): ?string;

    public function setAltText(?string $altText): void;
}
