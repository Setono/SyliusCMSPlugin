<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface TemplateInterface extends
    CodeAwareInterface,
    IdAwareInterface,
    InternalDescriptionAwareInterface,
    ResourceInterface,
    TimestampableInterface
{
    /**
     * This is the Twig source code of the template
     */
    public function getSource(): ?string;

    public function setSource(string $source): void;

    public function __toString(): string;
}
