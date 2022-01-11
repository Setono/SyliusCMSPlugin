<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Renderer\SuccessfulResponse;

final class Element
{
    public const TYPE_BLOCK = 'block';

    public const TYPE_CAROUSEL = 'carousel';

    public const TYPE_VIEW = 'view';

    private int $id;

    private string $identifier;

    private string $type;

    public function __construct(int $id, string $identifier, string $type)
    {
        $this->id = $id;
        $this->identifier = $identifier;
        $this->type = $type;
    }

    public static function fromSuccessfulResponse(SuccessfulResponse $response, string $type): self
    {
        return new self($response->getId(), $response->getIdentifier(), $type);
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
        return self::TYPE_BLOCK === $this->type;
    }

    public function isCarousel(): bool
    {
        return self::TYPE_CAROUSEL === $this->type;
    }

    public function isView(): bool
    {
        return self::TYPE_VIEW === $this->type;
    }
}
