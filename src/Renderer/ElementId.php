<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

final class ElementId
{
    private int $id;

    private string $identifier;

    private string $type;

    public function __construct(int $id, string $identifier, string $type)
    {
        $this->id = $id;
        $this->identifier = $identifier;
        $this->type = $type;
    }

    public static function fromResource(ElementInterface $element): self
    {
        return new self((int) $element->getId(), $element->getIdentifier(), $element->getType());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isBlock(): bool
    {
        return ElementInterface::TYPE_BLOCK === $this->type;
    }

    public function isCarousel(): bool
    {
        return ElementInterface::TYPE_CAROUSEL === $this->type;
    }

    public function isView(): bool
    {
        return ElementInterface::TYPE_VIEW === $this->type;
    }
}
