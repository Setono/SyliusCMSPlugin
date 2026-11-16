<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * The layouts of each content type. A layout's code is unique only within its content type.
 * See "Layouts" in docs/spec/layouts-and-presets.md
 */
interface LayoutRegistryInterface
{
    /**
     * Whether the code has the layout, which it has when it's disabled too
     */
    public function has(string $contentType, string $code): bool;

    /**
     * @throws UnknownLayoutException
     */
    public function get(string $contentType, string $code): LayoutMetadata;

    /**
     * Every layout of the content type, disabled ones too: the default first, then the others in the order they were
     * registered. None for a content type without its own URL
     *
     * @return list<LayoutMetadata>
     */
    public function all(string $contentType): array;

    /**
     * The content type's default layout
     *
     * @throws UnknownLayoutException when the content type has no layouts
     */
    public function getDefault(string $contentType): LayoutMetadata;

    /**
     * The layout an entry whose tree stores this layout code renders in: that layout, unless it was removed from code or
     * disabled, and then the content type's default layout. Null for a content type without layouts.
     * See "A removed layout" in docs/spec/layouts-and-presets.md
     */
    public function resolve(string $contentType, ?string $code): ?LayoutMetadata;
}
