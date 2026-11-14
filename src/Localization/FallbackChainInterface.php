<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

/**
 * The locales a translatable field falls back through, in order, when it's empty in the current locale
 */
interface FallbackChainInterface
{
    /**
     * @return non-empty-list<string> $localeCode first, ending at the store's fallback locale
     */
    public function localesFor(string $localeCode): array;
}
