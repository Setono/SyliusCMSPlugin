<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

use Sylius\Resource\Model\TranslatableInterface;

/**
 * The name a store record, such as a product attribute or an association type, has in a locale, read from its
 * translation there as it is. Not through getTranslation(), which adds an empty translation in a locale the record has
 * none in, and a later flush would save it
 *
 * @internal
 */
final class TranslatedName
{
    private function __construct()
    {
    }

    /**
     * Its name in the locale, or null when it has none there
     */
    public static function of(TranslatableInterface $record, string $localeCode): ?string
    {
        $translation = $record->getTranslations()->get($localeCode);
        $name = is_object($translation) && method_exists($translation, 'getName') ? $translation->getName() : null;

        return is_string($name) && '' !== $name ? $name : null;
    }
}
