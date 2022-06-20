<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface ViewBlockInterface extends IdAwareInterface, ResourceInterface
{
    public function getView(): ?ViewInterface;

    public function setView(?ViewInterface $view): void;

    public function getBlock(): ?BlockInterface;

    public function setBlock(?BlockInterface $block): void;

    public function getSection(): ?string;

    public function setSection(?string $section): void;

    public function getPosition(): int;

    public function setPosition(int $position): void;
}
