<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Carousel extends Element implements CarouselInterface
{
    /** @var Collection<array-key, SlideInterface> */
    protected Collection $slides;

    protected array $configuration = [
        'infinite' => false,
        'slidesToShow' => 1,
        'slidesToScroll' => 1,
        'autoplay' => false,
        'autoplaySpeed' => 3000,
    ];

    public function __construct()
    {
        $this->slides = new ArrayCollection();
    }

    public function getSlides(): Collection
    {
        return $this->slides;
    }

    public function hasSlide(SlideInterface $slide): bool
    {
        return $this->slides->contains($slide);
    }

    public function addSlide(SlideInterface $slide): void
    {
        if (!$this->hasSlide($slide)) {
            $this->slides->add($slide);
            $slide->setCarousel($this);
        }
    }

    public function removeSlide(SlideInterface $slide): void
    {
        if ($this->hasSlide($slide)) {
            $this->slides->removeElement($slide);
            $slide->setCarousel(null);
        }
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }
}
