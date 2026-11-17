<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Admin\ComingUp;
use Setono\SyliusCMSPlugin\Admin\ComingUpEvent;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
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
    ) {
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
     * The entries with unpublished changes, the one edited last first
     *
     * @return list<EntryInterface>
     */
    public function draftsInProgress(): array
    {
        $entries = $this->entries->findWithDraft(self::DRAFTS_IN_PROGRESS);
        $this->entryRows->load($entries);

        return $entries;
    }
}
