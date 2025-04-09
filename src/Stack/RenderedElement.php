<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Model\Asset;
use Setono\SyliusCMSPlugin\Model\Block;
use Setono\SyliusCMSPlugin\Model\Carousel;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;

final class RenderedElement
{
    public function __construct(
        public readonly string $type,
        public readonly string $code,
    ) {
    }

    public static function fromLogicalTemplateName(LogicalTemplateName $logicalTemplateName): self
    {
        return new self($logicalTemplateName->type, $logicalTemplateName->code);
    }

    public function isAsset(): bool
    {
        return Asset::getType() === $this->type;
    }

    public function isBlock(): bool
    {
        return Block::getType() === $this->type;
    }

    public function isCarousel(): bool
    {
        return Carousel::getType() === $this->type;
    }

    public function isPage(): bool
    {
        return Page::getType() === $this->type;
    }
}
