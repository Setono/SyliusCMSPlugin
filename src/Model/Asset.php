<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Asset implements AssetInterface
{
    protected ?int $id = null;

    protected ?string $path = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
