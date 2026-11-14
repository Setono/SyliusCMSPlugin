<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

final readonly class EntryUrlMatch
{
    /**
     * @param string|null $redirectTo the current path when the looked-up path is an old slug, otherwise null
     */
    public function __construct(
        public string $uuid,
        public string $contentType,
        public ?string $redirectTo = null,
    ) {
    }
}
