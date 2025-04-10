<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

interface EmbeddedVideoSlideInterface extends SlideInterface
{
    public function getDefaultEmbed(): ?string;

    public function setDefaultEmbed(?string $defaultEmbed): void;
}
