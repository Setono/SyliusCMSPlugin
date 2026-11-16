<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * Media items from the media library, of the kinds the field takes, in the order editors put them. Stored as an array
 * of media item UUIDs (ADR 0002), and hydrated as ItemList<MediaItemInterface>. Media lists are always hand-picked: no
 * source fills them. See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'media_list')]
final class MediaList extends Field
{
    use MediaKindsTrait;

    /**
     * @param list<'image'|'video'|'document'> $kinds the kinds of media item it takes
     * @param int|null $max the most media items it holds, or null for no most
     */
    public function __construct(
        string $label,
        public readonly array $kinds = ['image'],
        public readonly ?int $max = null,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
    ) {
        self::assertKinds($kinds);

        if (null !== $max && $max < 1) {
            throw new \InvalidArgumentException(sprintf('A %s field must hold at least one media item, or have no most, got a most of %d', self::class, $max));
        }

        parent::__construct($label, $help, $required, $translatable);
    }
}
