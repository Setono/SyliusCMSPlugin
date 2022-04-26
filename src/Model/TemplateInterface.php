<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface TemplateInterface extends
    ResourceInterface,
    CodeAwareInterface,
    TimestampableInterface,
    InternalDescriptionAwareInterface
{
    public function getId(): ?int;

    public function getSource(): ?string;

    public function setSource(string $source): void;

    /**
     * @return list<string>
     */
    public function getSections(): array;

    /**
     * @param list<string> $sections
     */
    public function setSections(array $sections): void;

    public function addSection(string $section): void;

    public function __toString(): string;
}
