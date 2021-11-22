<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

class CarouselBlock implements CarouselBlockInterface
{
    protected ?int $id = null;

    protected int $priority = 0;

    protected ?CarouselInterface $carousel = null;

    protected ?BlockInterface $block = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
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
