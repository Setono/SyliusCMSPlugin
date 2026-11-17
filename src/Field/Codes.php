<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * How the Product, Taxon, Products and Taxons handlers read the Sylius codes they store (ADR 0002). Products and taxons
 * are links: an export leaves them as codes. See "References" in docs/spec/content-model.md
 *
 * @internal
 */
final class Codes
{
    /**
     * @phpstan-assert-if-true non-empty-string $value
     */
    public static function isCode(mixed $value): bool
    {
        return is_string($value) && '' !== $value;
    }

    /**
     * @phpstan-assert-if-true list<non-empty-string> $value
     */
    public static function isCodeList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!self::isCode($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The references to the records of the type, such as product, that the codes refer to, each once, skipping what
     * isn't a code: a value of any shape may be stored
     *
     * @param array<mixed> $codes
     *
     * @return list<Reference>
     */
    public static function references(string $type, array $codes): array
    {
        $references = [];
        foreach ($codes as $code) {
            if (self::isCode($code)) {
                $references[$code] ??= new Reference($type, $code, false);
            }
        }

        return array_values($references);
    }
}
