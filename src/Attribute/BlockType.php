<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Marks a class as a block type. Its public, typed properties with field attributes are its fields.
 * See "Block types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BlockType
{
    /**
     * @param string $code stored in each block's "type"; matches ^[a-z][a-z0-9_]*$ and never changes
     * @param string $label a translation key, shown in the picker and the tree
     * @param string $template the Twig template the default renderer renders
     * @param int $version bumped with an upgrader when stored data can't be read as it is
     * @param list<Allow> $children which block types the block may hold; empty means none
     * @param list<class-string>|null $parents the only block types this one may sit inside; null means anywhere a parent allows it
     * @param list<Style> $styles the block styles it offers; projects add more in config
     * @param string|null $thumbnail a public path to the picker's preview image
     * @param list<string> $needs the context keys it needs, such as "product". It's offered only where the entry provides
     *                            them or the snippet declares them
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $template,
        public int $version = 1,
        public array $children = [],
        public ?array $parents = null,
        public array $styles = [],
        public ?string $thumbnail = null,
        public array $needs = [],
    ) {
    }
}
