<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;

/**
 * The fingerprint a translated value records of the value it was translated from: the lowercase hex SHA-256 of that
 * value in canonical JSON, so the order of an object's keys doesn't change it. The builder computes the same one
 * (assets/builder/src/fingerprint.ts). See "Translation state" in docs/spec/content-model.md
 */
final class Fingerprint
{
    public static function of(mixed $value): string
    {
        return hash('sha256', CanonicalJson::encode($value));
    }

    /**
     * Whether the string is a fingerprint: 64 lowercase hex digits
     */
    public static function isValid(string $fingerprint): bool
    {
        return 1 === preg_match('/^[0-9a-f]{64}$/D', $fingerprint);
    }
}
