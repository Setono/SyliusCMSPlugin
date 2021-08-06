<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DTO\Block;

use Setono\SyliusCMSPlugin\Model\BlockInterface;

final class Block
{
    public string $code;

    public string $content;

    public function __construct(string $code, string $content)
    {
        $this->content = $content;
        $this->code = $code;
    }

    public static function createFromEntity(BlockInterface $block): self
    {
        return new self((string) $block->getCode(), (string) $block->getContent());
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
