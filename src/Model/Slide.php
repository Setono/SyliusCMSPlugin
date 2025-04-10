<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Slide implements SlideInterface
{
    protected ?int $id = null;

    protected int $position = 0;

    protected ?CarouselInterface $carousel = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = (int) $position;
    }

    public function getCarousel(): ?CarouselInterface
    {
        return $this->carousel;
    }

    public function setCarousel(?CarouselInterface $carousel): void
    {
        $this->carousel = $carousel;
    }
}
