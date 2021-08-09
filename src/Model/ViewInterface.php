<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface ViewInterface extends ResourceInterface, CodeAwareInterface
{
    public function getId(): ?int;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    public function getTemplate(): ?string;

    public function setTemplate(string $template): void;

    /**
     * Returns a collection of view blocks sorted by ViewBlockInterface::getPriority() descending
     *
     * @return Collection|ViewBlockInterface[]
     *
     * @psalm-return Collection<array-key, ViewBlockInterface>
     */
    public function getViewBlocks(): Collection;
}
