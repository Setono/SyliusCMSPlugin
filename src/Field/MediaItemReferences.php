<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Symfony\Component\Uid\Uuid;

/**
 * How the Media and Media list handlers read the media item UUIDs they store. A media item is a dependency: an export
 * brings it along. See "References" in docs/spec/content-model.md
 *
 * @internal
 */
final class MediaItemReferences
{
    public const TYPE = ReferenceType::MEDIA_ITEM;

    /**
     * @phpstan-assert-if-true string $value
     */
    public static function isUuid(mixed $value): bool
    {
        return is_string($value) && Uuid::isValid($value);
    }

    /**
     * The reference to the media item, by its UUID in the lower case it's stored and loaded in
     */
    public static function reference(string $uuid): Reference
    {
        return new Reference(self::TYPE, strtolower($uuid), true);
    }

    /**
     * The media item the UUID refers to, when the map holds it and the field takes its kind, or else null: a broken
     * reference is skipped, and so is a media item of a kind the field doesn't take
     */
    public static function resolve(Media|MediaList $field, string $uuid, ReferenceMap $references): ?MediaItemInterface
    {
        $mediaItem = $references->get(self::TYPE, strtolower($uuid));

        return $mediaItem instanceof MediaItemInterface && $field->takes($mediaItem) ? $mediaItem : null;
    }
}
