<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface CarouselBlockInterface extends ResourceInterface
{
    public function getPosition(): int;

    public function setPosition(int $position): void;

    public function getCarousel(): ?CarouselInterface;

    public function setCarousel(?CarouselInterface $carousel): void;

    public function getBlock(): ?BlockInterface;

    public function setBlock(?BlockInterface $block): void;
}
