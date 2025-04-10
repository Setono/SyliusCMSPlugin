<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class EmbeddedVideoSlide extends Slide implements EmbeddedVideoSlideInterface
{
    protected ?string $defaultEmbed = null;

    public function getDefaultEmbed(): ?string
    {
        return $this->defaultEmbed;
    }

    public function setDefaultEmbed(?string $defaultEmbed): void
    {
        $this->defaultEmbed = $defaultEmbed;
    }
}
