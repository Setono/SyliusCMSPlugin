<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The publishing state the builder shows. See "Publishing" in docs/spec/publishing.md
 */
final class EntryStatus
{
    public const NEVER_PUBLISHED = 'never_published';

    public const PUBLISHED = 'published';

    public static function of(EntryInterface $entry): string
    {
        return $entry->isPublished() ? self::PUBLISHED : self::NEVER_PUBLISHED;
    }
}
