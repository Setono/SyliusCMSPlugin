<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Allow;

/**
 * A block type as the registry knows it: its #[BlockType] options and its fields
 */
final readonly class BlockTypeMetadata
{
    /**
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     * @param list<Allow> $children which block types it may hold, as declared
     * @param list<class-string>|null $parents the only block types it may sit inside, or null for anywhere a parent allows it
     * @param bool $disabled switched off in setono_sylius_cms.disabled.block_types: editors can't add it,
     *                       but blocks of the type already in trees keep rendering
     */
    public function __construct(
        public string $code,
        public string $label,
        public int $version,
        public string $template,
        public string $class,
        public array $fields,
        public array $children,
        public ?array $parents,
        public ?string $thumbnail,
        public bool $disabled,
    ) {
    }
}
