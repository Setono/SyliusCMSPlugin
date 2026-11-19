<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * How the admin and the builder name a collection, a content type's list and an Entries display, each by the first of
 * its translation keys that has a translation, or else by a label. The hub, the entry lists, From a collection's
 * choices and the Entries block's display choices all name them through here. See "Navigation" in docs/spec/admin.md
 */
final readonly class CollectionNames
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * A collection's name, setono_sylius_cms.collection.<code> such as Blog, or else its entries' plural, or else their
     * label
     */
    public function collection(CollectionMetadata $collection, ?string $localeCode = null): string
    {
        $code = $collection->code();

        return $this->firstTranslation([sprintf('setono_sylius_cms.collection.%s', $code), self::pluralKey($code)], $localeCode)
            ?? $this->translator->trans($collection->entries->label, [], null, $localeCode);
    }

    /**
     * A content type's plural, setono_sylius_cms.content_type_plural.<code> such as Posts, which names its list, or else
     * its label
     */
    public function plural(ContentTypeMetadata $contentType, ?string $localeCode = null): string
    {
        return $this->firstTranslation([self::pluralKey($contentType->code)], $localeCode)
            ?? $this->translator->trans($contentType->label, [], null, $localeCode);
    }

    /**
     * An Entries display's name, setono_sylius_cms.choice.entries_display.<display> such as Grid, or else the display
     * itself, as a project's own display may have no translation
     */
    public function display(string $display, ?string $localeCode = null): string
    {
        return $this->firstTranslation([sprintf('setono_sylius_cms.choice.entries_display.%s', $display)], $localeCode) ?? $display;
    }

    /**
     * The first of the keys that has a translation in the locale, translated, or null when none has
     *
     * @param list<string> $keys
     */
    private function firstTranslation(array $keys, ?string $localeCode): ?string
    {
        foreach ($keys as $key) {
            $translation = $this->translator->trans($key, [], null, $localeCode);
            if ($translation !== $key) {
                return $translation;
            }
        }

        return null;
    }

    private static function pluralKey(string $code): string
    {
        return sprintf('setono_sylius_cms.content_type_plural.%s', $code);
    }
}
