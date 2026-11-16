<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * The plugin writes its date and time columns in UTC, but Doctrine hydrates them in PHP's default time zone, so they're
 * read back as UTC through here
 *
 * @internal
 */
final class UtcColumn
{
    private function __construct()
    {
    }

    /**
     * @return ($column is null ? null : \DateTimeImmutable)
     */
    public static function read(?\DateTimeImmutable $column): ?\DateTimeImmutable
    {
        return null === $column ? null : new \DateTimeImmutable($column->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
    }
}
