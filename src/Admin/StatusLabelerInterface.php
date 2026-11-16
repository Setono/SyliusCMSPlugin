<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The status labels of an entry, from its state and its on-save settings. See "Status labels" in docs/spec/admin.md
 */
interface StatusLabelerInterface
{
    /**
     * @param bool $hasDraft whether the entry has a draft. Lists load the drafts of a whole page of entries at once
     *
     * @return list<StatusLabel> the entry's state first, then its on-save settings
     */
    public function labelsOf(EntryInterface $entry, bool $hasDraft): array;
}
