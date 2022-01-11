<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

class Response
{
    private string $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function __toString(): string
    {
        return $this->getContent();
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
