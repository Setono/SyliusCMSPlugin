<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class ViewBlock implements ViewBlockInterface
{
    use IdAwareTrait;

    protected ?ViewInterface $view = null;

    protected ?BlockInterface $block = null;

    protected ?string $section = null;

    protected int $position = 0;

    public function getView(): ?ViewInterface
    {
        return $this->view;
    }

    public function setView(?ViewInterface $view): void
    {
        $this->view = $view;
    }

    public function getBlock(): ?BlockInterface
    {
        return $this->block;
    }

    public function setBlock(?BlockInterface $block): void
    {
        $this->block = $block;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): void
    {
        $this->section = $section;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position ?? 0;
    }
}
