<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A media item from the media library, of the kinds the field takes. Stored as the media item's UUID (ADR 0002), and
 * hydrated as ?MediaItemInterface. Editors pick it in the builder's media picker, which is the media library opened as a
 * picker. See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'media')]
final class Media extends Field
{
    use MediaKindsTrait;

    /**
     * @param list<'image'|'video'|'document'> $kinds the kinds of media item it takes
     */
    public function __construct(
        string $label,
        public readonly array $kinds = ['image'],
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
    ) {
        self::assertKinds($kinds);

        parent::__construct($label, $help, $required, $translatable);
    }
}
