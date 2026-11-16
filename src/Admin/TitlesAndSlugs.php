<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

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
     * @return array<string, array{title: ?string, slug: ?string}> locale => the title and slug
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
     * A draft is the entry in the entry format, whose translations hold the title and slug among their fields
     *
     * @return array<string, array{title: ?string, slug: ?string}> locale => the title and slug
     */
    public static function ofDraft(DraftInterface $draft): array
    {
        $data = CanonicalJson::decode($draft->getContent());
        $data = is_array($data) ? $data['translations'] ?? null : null;

        $translations = [];
        foreach (is_array($data) ? $data : [] as $localeCode => $translation) {
            if (!is_string($localeCode) || !is_array($translation)) {
                continue;
            }

            $fields = $translation['fields'] ?? null;
            $fields = is_array($fields) ? $fields : [];
            $translations[$localeCode] = ['title' => self::string($fields, 'title'), 'slug' => self::string($fields, 'slug')];
        }

        return $translations;
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    private static function string(array $fields, string $field): ?string
    {
        return is_string($fields[$field] ?? null) ? $fields[$field] : null;
    }
}
