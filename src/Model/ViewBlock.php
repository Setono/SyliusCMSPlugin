<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class ViewBlock implements ViewBlockInterface
{
    protected ?int $id = null;

    protected ?ViewInterface $view = null;

    protected ?BlockInterface $block = null;

    protected ?string $location = null;

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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }
}
