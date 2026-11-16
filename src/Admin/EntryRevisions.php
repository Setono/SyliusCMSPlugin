<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;

/**
 * The revisions of an entry, newest first, which the Revisions section of its overview page lists through
 * cms_admin_entry_revisions(). See "Entry overview page" in docs/spec/admin.md
 */
final readonly class EntryRevisions
{
    public function __construct(private RevisionRepositoryInterface $revisions)
    {
    }

    /**
     * @return list<RevisionInterface>
     */
    public function of(EntryInterface $entry): array
    {
        return $this->revisions->findByEntry($entry);
    }
}
