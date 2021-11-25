<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class CarouselBlock implements CarouselBlockInterface
{
    protected ?int $id = null;

    protected int $position = 0;

    protected ?CarouselInterface $carousel = null;

    protected ?BlockInterface $block = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCarousel(): ?CarouselInterface
    {
        return $this->carousel;
    }

    public function setCarousel(?CarouselInterface $carousel): void
    {
        $this->carousel = $carousel;
    }

    public function getBlock(): ?BlockInterface
    {
        return $this->block;
    }

    public function setBlock(?BlockInterface $block): void
    {
        $this->block = $block;
    }
}
