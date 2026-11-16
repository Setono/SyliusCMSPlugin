<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

final readonly class EntryUrlMatch
{
    /**
     * @param string|null $redirectTo the current path when the looked-up path is an old slug, otherwise null
     * @param bool $homepage whether the entry is the channel's homepage, so its URL redirects to the channel's root,
     *                       /{locale}/ (docs/spec/urls-and-seo.md#rendering-the-homepage)
     */
    public function __construct(
        public string $uuid,
        public string $contentType,
        public ?string $redirectTo = null,
        public bool $homepage = false,
    ) {
    }
}
