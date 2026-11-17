<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * How a context check names another entry, such as the snippet a Snippet block shows: by its title in its first
 * translation that has one, or else by its UUID. Its locale isn't asked for, as Sylius's getTranslation() would add a
 * translation in a locale it doesn't have
 *
 * @internal
 */
final class EntryTitle
{
    private function __construct()
    {
    }

    public static function of(EntryInterface $entry): string
    {
        foreach ($entry->getTranslations() as $translation) {
            if ($translation instanceof EntryTranslationInterface && null !== $translation->getTitle() && '' !== $translation->getTitle()) {
                return $translation->getTitle();
            }
        }

        return $entry->getUuid();
    }
}
