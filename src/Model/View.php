<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class View implements ViewInterface
{
    protected ?int $id = null;

    protected ?string $code = null;

    protected bool $enabled = true;

    protected ?string $template = null;

    /** @var Collection<array-key, ViewBlockInterface> */
    protected Collection $viewBlocks;

    public function __construct()
    {
        $this->viewBlocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
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
}
