<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface BlockInterface extends ResourceInterface, ElementInterface, CodeAwareInterface
{
    public function getId(): ?int;

    public function getDefaultContent(): ?string;

    public function setDefaultContent(?string $defaultContent): void;

    public function getDefaultRawContent(): ?string;

    public function setDefaultRawContent(?string $defaultRawContent): void;

    public function getContent(): ?string;

    public function setContent(?string $content): void;

    public function __toString(): string;
}
