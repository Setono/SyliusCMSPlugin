<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;

interface CarouselInterface extends ElementInterface
{
    /**
     * @return Collection<array-key, CarouselBlockInterface>
     */
    public function getCarouselBlocks(): Collection;

    public function hasCarouselBlocks(): bool;

    public function hasCarouselBlock(CarouselBlockInterface $carouselBlock): bool;

    public function addCarouselBlock(CarouselBlockInterface $carouselBlock): void;

    public function removeCarouselBlock(CarouselBlockInterface $carouselBlock): void;

    public function getConfiguration(): array;

    public function setConfiguration(array $configuration): void;
}
