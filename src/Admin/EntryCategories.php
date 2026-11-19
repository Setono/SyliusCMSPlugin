<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;

/**
 * The categories the entry lists' Title column shows below an entry of a collection with categories, as its working
 * copy holds them: read from its draft's JSON when it has one (ADR 0009), else from its rows. The categories of a page
 * of entries are loaded in one query, and each is named by its title in the first of the locales it has. See "Entry
 * lists" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class EntryCategories
{
    public function __construct(
        private IndexedFields $indexedFields,
        private ContentTypeRegistryInterface $contentTypes,
        private EntryRepositoryInterface $entries,
    ) {
    }

    /**
     * @param list<EntryInterface> $entries
     * @param array<string, DraftInterface> $drafts the entries' drafts, by the entry's UUID
     * @param list<string> $locales the locales to name a category in, the first it has
     *
     * @return array<string, list<string>> the titles of each entry's categories, in the editor's order, by the entry's
     *                                     UUID, for the entries of a collection with categories
     */
    public function titlesOf(array $entries, array $drafts, array $locales): array
    {
        $uuidsByEntry = [];
        foreach ($entries as $entry) {
            $field = $this->indexedFields->categoryField($this->contentTypes->getByEntry($entry));
            if (null === $field) {
                continue;
            }

            $draft = $drafts[$entry->getUuid()] ?? null;
            $uuidsByEntry[$entry->getUuid()] = null === $draft ? $this->indexedFields->categoriesOf($entry) : self::inDraft($draft, $field->name);
        }

        $uuids = array_values(array_unique(array_merge([], ...array_values($uuidsByEntry))));
        if ([] === $uuids) {
            return array_map(static fn (): array => [], $uuidsByEntry);
        }

        $titles = [];
        foreach ($this->entries->findByUuids($uuids) as $category) {
            $titles[strtolower($category->getUuid())] = self::title($category, $locales);
        }

        return array_map(
            static fn (array $categories): array => array_values(array_filter(array_map(static fn (string $uuid): ?string => $titles[$uuid] ?? null, $categories), static fn (?string $title): bool => null !== $title)),
            $uuidsByEntry,
        );
    }

    /**
     * @return list<string>
     */
    private static function inDraft(DraftInterface $draft, string $field): array
    {
        try {
            $data = CanonicalJson::decode($draft->getContent());
        } catch (\JsonException) {
            return [];
        }

        return IndexedFields::uuids(is_array($data) && is_array($data['fields'] ?? null) ? $data['fields'][$field] ?? null : null);
    }

    /**
     * @param list<string> $locales
     */
    private static function title(EntryInterface $category, array $locales): string
    {
        $titles = array_filter(array_map(static fn (array $translation): ?string => $translation['title'], TitlesAndSlugs::ofRows($category)), static fn (?string $title): bool => null !== $title && '' !== $title);
        foreach ($locales as $localeCode) {
            if (isset($titles[$localeCode])) {
                return $titles[$localeCode];
            }
        }

        return [] === $titles ? $category->getUuid() : (string) reset($titles);
    }
}
