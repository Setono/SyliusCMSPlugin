<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class ViewBlock implements ViewBlockInterface
{
    protected ?int $id = null;

    protected ?ViewInterface $view = null;

    protected ?BlockInterface $block = null;

    protected ?string $section = null;

    protected int $priority = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }
}
