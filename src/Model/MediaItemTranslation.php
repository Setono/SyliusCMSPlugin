<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\AbstractTranslation;

class MediaItemTranslation extends AbstractTranslation implements MediaItemTranslationInterface
{
    protected ?int $id = null;

    protected ?string $altText = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): void
    {
        $this->altText = $altText;
    }
}
