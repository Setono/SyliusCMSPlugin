<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The rows of the admin's entry lists. A list loads the rows of its page of entries at once, with the page's drafts in
 * one query, their scheduled publishes in another and their problem counts in a third, and the field templates of its grid then get each entry's row through cms_admin_entry_row(). A draft's
 * title and slug are read from its JSON, so lists show the working copy (ADR 0009)
 */
final class EntryRows implements ResetInterface
{
    /** @var array<string, EntryRow> by the entry's UUID */
    private array $rows = [];

    public function __construct(
        private readonly DraftRepositoryInterface $drafts,
        private readonly ContentTypeRegistryInterface $contentTypes,
        private readonly StatusLabelerInterface $statusLabeler,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslationLocaleProviderInterface $translationLocaleProvider,
        private readonly LocaleContextInterface $localeContext,
        private readonly ScheduledPublishRepositoryInterface $scheduledPublishes,
        private readonly ClockInterface $clock,
        private readonly ProblemCountProviderInterface $problemCounts,
    ) {
    }

    /**
     * Builds and keeps the rows of a page of entries, in the admin user's locale
     *
     * @param iterable<EntryInterface> $entries
     */
    public function load(iterable $entries): void
    {
        $entries = iterator_to_array($entries, false);
        if ([] === $entries) {
            return;
        }

        /** @var array<string, DraftInterface> $drafts */
        $drafts = [];
        foreach ($this->drafts->findByEntries($entries) as $draft) {
            $drafts[$draft->getEntry()->getUuid()] = $draft;
        }

        /** @var array<string, ScheduledPublishInterface> $scheduledPublishes */
        $scheduledPublishes = [];
        foreach ($this->scheduledPublishes->findByEntries($entries) as $scheduledPublish) {
            $scheduledPublishes[$scheduledPublish->getEntry()->getUuid()] = $scheduledPublish;
        }

        $problems = $this->problemCounts->getCounts($entries);

        $locales = [$this->localeContext->getLocaleCode(), $this->translationLocaleProvider->getDefaultLocaleCode()];

        foreach ($entries as $entry) {
            $this->rows[$entry->getUuid()] = $this->build($entry, $drafts[$entry->getUuid()] ?? null, $scheduledPublishes[$entry->getUuid()] ?? null, $problems[$entry->getUuid()] ?? new ProblemCount(), $locales);
        }
    }

    /**
     * The entry's row, as it was loaded with its page, or else loaded alone
     */
    public function get(EntryInterface $entry): EntryRow
    {
        if (!isset($this->rows[$entry->getUuid()])) {
            $this->load([$entry]);
        }

        return $this->rows[$entry->getUuid()];
    }

    public function reset(): void
    {
        $this->rows = [];
    }

    /**
     * @param list<string> $locales the locales to show the first of that the entry has: the admin user's, then the store's default
     */
    private function build(EntryInterface $entry, ?DraftInterface $draft, ?ScheduledPublishInterface $scheduledPublish, ProblemCount $problems, array $locales): EntryRow
    {
        $contentType = $this->contentTypes->getByEntry($entry);

        $translations = null === $draft ? TitlesAndSlugs::ofRows($entry) : TitlesAndSlugs::ofDraft($draft);
        $localeCode = self::localeShown($translations, $locales);
        $translation = null === $localeCode ? ['title' => null, 'slug' => null] : $translations[$localeCode];
        $path = EntryPath::ofSlug($contentType, $translation['slug']);

        return new EntryRow(
            $entry,
            $contentType,
            $translation['title'],
            null === $path ? null : $this->urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => $localeCode, 'path' => $path]),
            $this->statusLabeler->labelsOf($entry, null !== $draft, $scheduledPublish),
            EntryStatus::of($entry, null !== $draft),
            $draft,
            $scheduledPublish,
            null === $scheduledPublish ? null : EntryStatus::ofScheduledPublish($scheduledPublish, $this->clock->now()),
            $problems,
        );
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
