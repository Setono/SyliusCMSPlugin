<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the rows of a page of an entry list. It loads the page's drafts in one query and reads a draft's title and
 * slug from its JSON, so lists show the working copy (ADR 0009)
 */
final readonly class EntryRows
{
    public function __construct(
        private DraftRepositoryInterface $drafts,
        private ContentTypeRegistryInterface $contentTypes,
        private StatusLabelerInterface $statusLabeler,
        private UrlGeneratorInterface $urlGenerator,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
    ) {
    }

    /**
     * @param iterable<EntryInterface> $entries
     * @param string $localeCode the admin user's locale
     *
     * @return list<EntryRow>
     */
    public function build(iterable $entries, string $localeCode): array
    {
        $entries = [...$entries];
        if ([] === $entries) {
            return [];
        }

        /** @var array<string, DraftInterface> $drafts */
        $drafts = [];
        foreach ($this->drafts->findByEntries($entries) as $draft) {
            $drafts[$draft->getEntry()->getUuid()] = $draft;
        }

        $locales = [$localeCode, $this->translationLocaleProvider->getDefaultLocaleCode()];

        return array_map(function (EntryInterface $entry) use ($drafts, $locales): EntryRow {
            $draft = $drafts[$entry->getUuid()] ?? null;
            $contentType = $this->contentTypes->getByEntry($entry);

            $translations = null === $draft ? self::translationsOfRows($entry) : self::translationsOfDraft($draft);
            $localeCode = self::localeShown($translations, $locales);
            $translation = null === $localeCode ? ['title' => null, 'slug' => null] : $translations[$localeCode];
            $path = EntryPath::ofSlug($contentType, $translation['slug']);

            return new EntryRow(
                $entry,
                $contentType,
                $translation['title'],
                null === $path ? null : $this->urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => $localeCode, 'path' => $path]),
                $this->statusLabeler->labelsOf($entry, null !== $draft),
            );
        }, $entries);
    }

    /**
     * @return array<string, array{title: ?string, slug: ?string}> locale => the title and slug
     */
    private static function translationsOfRows(EntryInterface $entry): array
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
    private static function translationsOfDraft(DraftInterface $draft): array
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

    /**
     * The first of the locales that the entry has, or else its first translation's
     *
     * @param array<string, mixed> $translations
     * @param list<string> $locales
     */
    private static function localeShown(array $translations, array $locales): ?string
    {
        foreach ($locales as $localeCode) {
            if (array_key_exists($localeCode, $translations)) {
                return $localeCode;
            }
        }

        return array_key_first($translations);
    }
}
