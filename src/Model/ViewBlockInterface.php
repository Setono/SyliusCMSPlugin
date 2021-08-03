<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface ViewBlockInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getView(): ?ViewInterface;

    public function setView(?ViewInterface $view): void;

    public function getBlock(): ?BlockInterface;

    public function setBlock(?BlockInterface $block): void;

    public function getLocation(): ?string;

    public function setLocation(?string $location): void;
}
