<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * A block type as the registry knows it: its #[BlockType] options and its fields
 */
final readonly class BlockTypeMetadata
{
    /**
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     */
    public function __construct(
        public string $code,
        public string $label,
        public int $version,
        public string $template,
        public string $class,
        public array $fields,
    ) {
    }
}
