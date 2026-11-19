<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * An entry, picked by searching the entries, of its content type when it names one. Stored as the entry's UUID
 * (ADR 0002), a link an export leaves as a UUID, and hydrated as ?EntryInterface: the entry while the shopper may see it.
 * See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'entry')]
final class Entry extends Field
{
    /**
     * @param class-string|null $contentType the content type the entry is of, by its model or a class or interface the
     *                                       model extends, such as BlogCategory::class, or null for any
     */
    public function __construct(
        string $label,
        public readonly ?string $contentType = null,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
    ) {
        parent::__construct($label, $help, $required, $translatable);
    }
}
