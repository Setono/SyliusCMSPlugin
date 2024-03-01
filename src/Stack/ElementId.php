<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Model\ElementInterface;

final class ElementId
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $identifier,
        public readonly string $type,
    ) {
    }

    public function isAsset(): bool
    {
        return ElementInterface::TYPE_ASSET === $this->type;
    }

    public function isBlock(): bool
    {
        return ElementInterface::TYPE_BLOCK === $this->type;
    }

    public function isCarousel(): bool
    {
        return ElementInterface::TYPE_CAROUSEL === $this->type;
    }

    public function isPage(): bool
    {
        return ElementInterface::TYPE_PAGE === $this->type;
    }
}
