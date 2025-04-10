<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;

interface CarouselInterface extends ElementInterface
{
    /**
     * @return Collection<array-key, SlideInterface>
     */
    public function getSlides(): Collection;

    public function hasSlide(SlideInterface $slide): bool;

    public function addSlide(SlideInterface $slide): void;

    public function removeSlide(SlideInterface $slide): void;

    public function getConfiguration(): array;

    public function setConfiguration(array $configuration): void;
}
