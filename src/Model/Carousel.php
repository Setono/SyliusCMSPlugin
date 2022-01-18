<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Carousel implements CarouselInterface
{
    use InternalDescriptionAwareTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    /** @var Collection<array-key, CarouselBlockInterface> */
    protected Collection $carouselBlocks;

    protected array $configuration = [
        'infinite' => false,
        'slidesToShow' => 1,
        'slidesToScroll' => 1,
        'autoplay' => false,
        'autoplaySpeed' => 3000,
    ];

    public function __construct()
    {
        $this->carouselBlocks = new ArrayCollection();
    }

    public function getIdentifier(): string
    {
        return sprintf('sscms-carousel-%s', (string) $this->getCode());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return 'carousel';
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getCarouselBlocks(): Collection
    {
        return $this->carouselBlocks;
    }

    public function hasCarouselBlocks(): bool
    {
        return !$this->getCarouselBlocks()->isEmpty();
    }

    public function hasCarouselBlock(CarouselBlockInterface $carouselBlock): bool
    {
        return $this->getCarouselBlocks()->contains($carouselBlock);
    }

    public function addCarouselBlock(CarouselBlockInterface $carouselBlock): void
    {
        if (!$this->hasCarouselBlock($carouselBlock)) {
            $this->carouselBlocks->add($carouselBlock);
            $carouselBlock->setCarousel($this);
        }
    }

    public function removeCarouselBlock(CarouselBlockInterface $carouselBlock): void
    {
        if ($this->hasCarouselBlock($carouselBlock)) {
            $this->carouselBlocks->removeElement($carouselBlock);
            $carouselBlock->setCarousel(null);
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
