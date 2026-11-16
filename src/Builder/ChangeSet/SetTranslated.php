<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Sets where a block's translatable value in one locale was translated from: the locale and the fingerprint of the
 * value it was translated from, as "Copy it here", typing over a fallback value and "Mark as up to date" record it.
 * See "Translation state" in docs/spec/content-model.md
 */
final readonly class SetTranslated implements Change
{
    /**
     * @param string $key the block's key
     * @param array{from: string, fingerprint: string}|null $translated null when the value has no source
     */
    public function __construct(
        public string $key,
        public string $field,
        public string $locale,
        public ?array $translated,
    ) {
    }
}
