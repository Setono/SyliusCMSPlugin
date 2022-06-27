<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Asset extends Element implements AssetInterface
{
    protected ?string $name = null;

    protected ?string $path = null;

    protected ?string $mimeType = null;

    public function getType(): string
    {
        return self::TYPE_ASSET;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }
}
