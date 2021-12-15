<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface ViewInterface extends
    ResourceInterface,
    ElementInterface,
    CodeAwareInterface,
    InternalDescriptionAwareInterface
{
    public function getId(): ?int;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    public function getTemplate(): ?string;

    public function setTemplate(string $template): void;

    /**
     * Returns a collection of view blocks sorted by ViewBlockInterface::getPosition() descending
     *
     * @return Collection|ViewBlockInterface[]
     *
     * @psalm-return Collection<array-key, ViewBlockInterface>
     */
    public function getViewBlocks(): Collection;

    public function hasViewBlock(ViewBlockInterface $block): bool;

    public function addViewBlock(ViewBlockInterface $block, ?string $key = null): void;

    public function removeViewBlock(ViewBlockInterface $block): void;

    /**
     * @return Collection|ViewBlockInterface[]
     *
     * @psalm-return Collection<array-key, ViewBlockInterface>
     */
    public function getViewBlocksInSection(string $sectionName): Collection;

    /**
     * @return Collection|BlockInterface[]
     *
     * @psalm-return Collection<array-key, BlockInterface|null>
     */
    public function getBlocksInSection(string $sectionName): Collection;

    public function __toString(): string;
}
