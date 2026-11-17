<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\InvalidMediaChangeException;

/**
 * Reads a value of a media endpoint's request body as the type it must be
 *
 * @internal
 */
final class BodyValue
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidMediaChangeException when it isn't a string
     */
    public static function string(mixed $value, string $name): string
    {
        return is_string($value) ? $value : throw new InvalidMediaChangeException(sprintf('The %s must be a string', $name));
    }

    /**
     * @throws InvalidMediaChangeException when it's neither null nor a string
     */
    public static function nullableString(mixed $value, string $name): ?string
    {
        return null === $value ? null : self::string($value, $name);
    }
}
