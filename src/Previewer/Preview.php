<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

final class Preview implements \Stringable
{
    public function __construct(
        /**
         * The HTML - ready to output
         */
        private readonly string $content,
    ) {
    }

    public static function createUnavailablePreview(): self
    {
        return new self('No preview available');
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
