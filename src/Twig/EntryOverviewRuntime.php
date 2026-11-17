<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Admin\ChannelHomepage;
use Setono\SyliusCMSPlugin\Admin\ChannelHomepages;
use Setono\SyliusCMSPlugin\Admin\EntryPlacements;
use Setono\SyliusCMSPlugin\Admin\EntryPlacementsSection;
use Setono\SyliusCMSPlugin\Admin\EntryRow;
use Setono\SyliusCMSPlugin\Admin\EntryShareLink;
use Setono\SyliusCMSPlugin\Admin\EntryShareLinks;
use Setono\SyliusCMSPlugin\Admin\EntryUrl;
use Setono\SyliusCMSPlugin\Admin\EntryUrls;
use Setono\SyliusCMSPlugin\Admin\ShareLinkTarget;
use Setono\SyliusCMSPlugin\Admin\WhereItShows;
use Setono\SyliusCMSPlugin\Admin\WhereItShowsGrid;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The cms_admin_* functions of the entry overview page's sections, which read what each section shows. See "Entry
 * overview page" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class EntryOverviewRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EntryUrls $entryUrls,
        private ChannelHomepages $channelHomepages,
        private ProblemFinderInterface $problemFinder,
        private RevisionRepositoryInterface $revisions,
        private EntryShareLinks $shareLinks,
        private WhereItShows $whereItShows,
        private EntryPlacements $entryPlacements,
    ) {
    }

    /**
     * A snippet's placements and the slots it can be placed in, or null for an entry that isn't a snippet
     */
    public function entryPlacements(EntryInterface $entry): ?EntryPlacementsSection
    {
        return $this->entryPlacements->of($entry);
    }

    /**
     * @return list<EntryUrl>
     */
    public function entryUrls(EntryInterface $entry): array
    {
        return $this->entryUrls->of($entry);
    }

    /**
     * @return list<ChannelHomepage>
     */
    public function channelHomepages(EntryInterface $entry): array
    {
        return $this->channelHomepages->of($entry);
    }

    /**
     * @return list<Problem>
     */
    public function entryProblems(EntryInterface $entry): array
    {
        return $this->problemFinder->findByEntry($entry);
    }

    /**
     * The entry's revisions, newest first
     *
     * @return list<RevisionInterface>
     */
    public function entryRevisions(EntryInterface $entry): array
    {
        return $this->revisions->findByEntry($entry);
    }

    /**
     * @return list<EntryShareLink> newest first
     */
    public function entryShareLinks(EntryInterface $entry): array
    {
        return $this->shareLinks->of($entry);
    }

    /**
     * @return list<ShareLinkTarget>
     */
    public function shareLinkTargets(EntryInterface $entry): array
    {
        return $this->shareLinks->targets($entry);
    }

    public function whereItShows(EntryRow $row): WhereItShowsGrid
    {
        return $this->whereItShows->of($row);
    }
}
