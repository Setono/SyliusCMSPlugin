<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface AssetInterface extends IdAwareInterface, ResourceInterface, InternalDescriptionAwareInterface
{
    public function getName(): ?string;

    public function setName(string $name): void;

    public function getPath(): ?string;

    public function setPath(string $path): void;

    public function getMimeType(): ?string;

    public function setMimeType(?string $mimeType): void;
}
