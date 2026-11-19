<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translation key in a code preset file, {"$trans": …, "$domain": …}, in place of a text: the preset's name, category
 * or description, or a translatable text-like field's value. Inserting the preset resolves it for each of the entry's
 * locales. See "Field values" in docs/spec/content-model.md and "Preset file format" in docs/spec/layouts-and-presets.md
 */
final readonly class TranslationKey implements TranslatableInterface
{
    public const KEY = '$trans';

    public const DOMAIN = '$domain';

    public const DEFAULT_DOMAIN = 'messages';

    public function __construct(
        public string $key,
        public string $domain = self::DEFAULT_DOMAIN,
    ) {
    }

    /**
     * The translation key a stored value is, or null when it isn't one: a map with a "$trans" string, an optional
     * "$domain" string, and nothing else
     */
    public static function fromValue(mixed $value): ?self
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (!is_array($value) || !array_key_exists(self::KEY, $value) || [] !== array_diff(array_keys($value), [self::KEY, self::DOMAIN])) {
            return null;
        }

        $key = $value[self::KEY];
        $domain = $value[self::DOMAIN] ?? self::DEFAULT_DOMAIN;
        if (!is_string($key) || '' === $key || !is_string($domain) || '' === $domain) {
            return null;
        }

        return new self($key, $domain);
    }

    /**
     * A preset's name, category or description, or another text a translation key may stand in for, as the admin user
     * reads it: the key translated, or the text as it is
     *
     * @return ($text is null ? null : string)
     */
    public static function textOf(string|self|null $text, TranslatorInterface $translator, ?string $locale = null): ?string
    {
        return $text instanceof self ? $text->trans($translator, $locale) : $text;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->key, [], $this->domain, $locale);
    }

    /**
     * As a stored value, leaving out the default domain
     *
     * @return array<string, string>
     */
    public function toValue(): array
    {
        return self::DEFAULT_DOMAIN === $this->domain ? [self::KEY => $this->key] : [self::KEY => $this->key, self::DOMAIN => $this->domain];
    }
}
