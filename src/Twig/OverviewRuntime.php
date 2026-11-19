<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Admin\ChannelCompleteness;
use Setono\SyliusCMSPlugin\Admin\ComingUp;
use Setono\SyliusCMSPlugin\Admin\ComingUpEvent;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\NeedsAttention;
use Setono\SyliusCMSPlugin\Admin\NeedsAttentionGroups;
use Setono\SyliusCMSPlugin\Admin\ProblemGroup;
use Setono\SyliusCMSPlugin\Admin\TranslationCompleteness;
use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Media\MediaLocales;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\PresetProblem;
use Setono\SyliusCMSPlugin\Problem\PresetProblemFinderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The cms_admin_* functions of the Overview's panels, which read what each panel shows. The entries a panel lists get
 * their rows in one go, so its template reads each with cms_admin_entry_row(). See "Overview" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class OverviewRuntime implements RuntimeExtensionInterface
{
    /** How many entries Drafts in progress lists */
    public const DRAFTS_IN_PROGRESS = 10;

    public function __construct(
        private ComingUp $comingUp,
        private EntryRows $entryRows,
        private EntryRepositoryInterface $entries,
        private NeedsAttention $needsAttention,
        private MediaItemRepositoryInterface $mediaItems,
        private MediaLocales $mediaLocales,
        private TranslationCompleteness $translationCompleteness,
        private PresetProblemFinderInterface $presetProblems,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * The problems of presets, which aren't entries, grouped by kind, which Needs attention lists below the entries'
     *
     * @return list<ProblemGroup<PresetProblem>>
     */
    public function presetProblems(): array
    {
        return ProblemGroup::byKind($this->presetProblems->findAll());
    }

    /**
     * @return list<ChannelCompleteness>
     */
    public function translationCompleteness(): array
    {
        return $this->translationCompleteness->byChannel();
    }

    public function needsAttention(): NeedsAttentionGroups
    {
        $groups = $this->needsAttention->find();
        $this->entryRows->load($groups->entries);

        return $groups;
    }

    /**
     * How many images miss alt text in one or more of the store's locales, which Needs attention flags. It's what the
     * media library's missing alt text filter finds. See "Metadata" in docs/spec/media.md
     */
    public function missingAltText(): int
    {
        return $this->mediaItems->createLibraryPaginator(new MediaFilter(missingAltIn: $this->mediaLocales->codes()))->getNbResults();
    }

    /**
     * @return list<ComingUpEvent>
     */
    public function comingUp(): array
    {
        $events = $this->comingUp->events();
        $this->entryRows->load(array_map(static fn (ComingUpEvent $event) => $event->entry, $events));

        return $events;
    }

    /**
     * The entries with unpublished changes and those never published, the one edited last first
     *
     * @return list<EntryInterface>
     */
    public function draftsInProgress(): array
    {
        $entries = $this->entries->findDraftsInProgress(self::DRAFTS_IN_PROGRESS, $this->contentTypes->disabledCodes());
        $this->entryRows->load($entries);

        return $entries;
    }
}
