<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The entry state the builder shows. See "Entry states" in docs/spec/publishing.md
 */
final class EntryStatus
{
    public const NEVER_PUBLISHED = 'never_published';

    public const PUBLISHED = 'published';

    public const UNPUBLISHED_CHANGES = 'unpublished_changes';

    public static function of(EntryInterface $entry, bool $hasDraft): string
    {
        if (!$entry->isPublished()) {
            return self::NEVER_PUBLISHED;
        }

        return $hasDraft ? self::UNPUBLISHED_CHANGES : self::PUBLISHED;
    }
}
