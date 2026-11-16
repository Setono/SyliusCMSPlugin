<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Sets where a value of one of the entry's own translatable fields, such as a translation's title, was translated from.
 * Setting it in a locale the entry has no translation in adds the translation. See "Translation state" in
 * docs/spec/content-model.md
 */
final readonly class SetEntryTranslated implements Change
{
    /**
     * @param array{from: string, fingerprint: string}|null $translated null when the value has no source
     */
    public function __construct(
        public string $field,
        public string $locale,
        public ?array $translated,
    ) {
    }
}
