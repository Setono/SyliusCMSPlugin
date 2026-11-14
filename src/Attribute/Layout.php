<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Marks a class as a layout of a content type with its own URL. Its public, typed properties with field attributes
 * are its fields, stored in the tree's "layout.fields". See "Layouts" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Layout
{
    /**
     * @param string $code stored in the tree's "layout.code"; unique within its content type, and never changes
     * @param class-string $contentType the content type it belongs to, which must have its own URL
     * @param string $label a translation key
     * @param int $version versions its fields, like a block type's: bumped with an upgrader when stored data can't be read as it is
     * @param string|null $thumbnail the wireframe card in the create dialog and the entry settings
     * @param bool $default marks the content type's default layout
     * @param list<string> $shows the entry fields its template prints, by property name
     */
    public function __construct(
        public string $code,
        public string $contentType,
        public string $label,
        public string $template,
        public int $version = 1,
        public ?string $thumbnail = null,
        public bool $default = false,
        public array $shows = [],
    ) {
    }
}
