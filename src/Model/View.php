<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class View extends Element implements ViewInterface
{
    protected bool $enabled = true;

    protected ?string $template = null;

    /** @var Collection<array-key, ViewBlockInterface> */
    protected Collection $viewBlocks;

    public function __construct()
    {
        parent::__construct();

        $this->viewBlocks = new ArrayCollection();
    }

    public function getType(): string
    {
        return ElementInterface::TYPE_VIEW;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getViewBlocks(): Collection
    {
        return $this->viewBlocks;
    }

    public function hasViewBlock(ViewBlockInterface $block): bool
    {
        return $this->getViewBlocks()->contains($block);
    }

    public function addViewBlock(ViewBlockInterface $block, ?string $key = null): void
    {
        if (!$this->hasViewBlock($block)) {
            $this->viewBlocks->add($block);
            $block->setView($this);
        }
    }

    public function removeViewBlock(ViewBlockInterface $block): void
    {
        if ($this->hasViewBlock($block)) {
            $this->viewBlocks->removeElement($block);
        }
    }

    public function getViewBlocksInSection(string $sectionName): Collection
    {
        return $this->getViewBlocks()->filter(function (ViewBlockInterface $viewBlock) use ($sectionName): bool {
            return $sectionName === $viewBlock->getSection();
        });
    }

    public function getBlocksInSection(string $sectionName): Collection
    {
        return $this->getViewBlocksInSection($sectionName)->map(function (ViewBlockInterface $viewBlock): ?BlockInterface {
            return $viewBlock->getBlock();
        });
    }

    public function __toString(): string
    {
        return (string) $this->getCode();
    }
}
