<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface CarouselInterface extends ResourceInterface, CodeAwareInterface, ElementInterface
{
    public function getCode(): ?string;

    public function setCode(?string $code): void;

    public function getCarouselBlocks(): Collection;

    public function hasCarouselBlocks(): bool;

    public function hasCarouselBlock(CarouselBlockInterface $carouselBlock): bool;

    public function addCarouselBlock(CarouselBlockInterface $carouselBlock): void;

    public function removeCarouselBlock(CarouselBlockInterface $carouselBlock): void;

    public function getConfiguration(): array;

    public function setConfiguration(array $configuration): void;
}
