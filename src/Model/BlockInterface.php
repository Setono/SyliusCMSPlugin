<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface BlockInterface extends ResourceInterface, CodeAwareInterface
{
    public function getId(): ?int;

    public function getDefaultContent(): ?string;

    public function setDefaultContent(?string $defaultContent): void;

    public function getContent(): ?string;

    public function setContent(?string $content): void;
}
