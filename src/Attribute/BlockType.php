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
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $template,
        public int $version = 1,
    ) {
    }
}
