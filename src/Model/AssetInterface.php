<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

interface AssetInterface extends ElementInterface
{
    public function getName(): ?string;

    public function setName(string $name): void;

    public function getPath(): ?string;

    public function setPath(string $path): void;

    public function getMimeType(): ?string;

    public function setMimeType(?string $mimeType): void;
}
