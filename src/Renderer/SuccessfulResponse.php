<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

final class SuccessfulResponse extends Response
{
    private int $id;

    private string $identifier;

    public function __construct(int $id, string $identifier, string $content)
    {
        parent::__construct($content);

        $this->id = $id;
        $this->identifier = $identifier;
    }

    public static function fromElement(ElementInterface $element, string $content): self
    {
        return new self((int) $element->getId(), $element->getIdentifier(), $content);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
