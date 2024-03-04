<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Stack;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
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
