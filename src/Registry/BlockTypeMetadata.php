<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Style;

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
     * @param array<string, Style> $styles the block styles it offers, by code: those it declares, then those added in config
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
        public array $styles = [],
    ) {
    }

    /**
     * The class of the block style with this code, or null for no style. A style code that's no longer declared is no style
     */
    public function styleClass(?string $code): ?string
    {
        return null === $code ? null : ($this->styles[$code] ?? null)?->class;
    }
}
