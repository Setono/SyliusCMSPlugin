<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The title and slug of each of an entry's translations, read from its rows or from its draft's JSON, which the admin
 * reads without loading the working copy (ADR 0009)
 *
 * @internal
 */
final class TitlesAndSlugs
{
    /**
     * @return array<string, array<string, string|null>> locale => the title and slug, by field
     */
    public static function ofRows(EntryInterface $entry): array
    {
        $translations = [];
        foreach ($entry->getTranslations() as $localeCode => $translation) {
            $translations[$localeCode] = ['title' => $translation->getTitle(), 'slug' => $translation->getSlug()];
        }

        return $translations;
    }

    /**
     * A draft is the entry in the entry format, read through EntryFormat. One that can't be read gives none, and fails
     * where it's opened
     *
     * @return array<string, array<string, string|null>> locale => the title and slug, by field
     */
    public static function ofDraft(DraftInterface $draft): array
    {
        try {
            return EntryDenormalizer::translationValues(CanonicalJson::decode($draft->getContent()), ['title', 'slug']);
        } catch (InvalidEntryFormatException|\JsonException) {
            return [];
        }
    }
}
