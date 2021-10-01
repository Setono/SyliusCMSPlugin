<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface AssetInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getPath(): ?string;

    public function setPath(string $path): void;
}
