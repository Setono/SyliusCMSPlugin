<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * The statuses an entry list filters on, in the order the filter offers them. See "Entry lists" in docs/spec/admin.md.
 * Needs attention (#231) and Scheduled (#241) join with their tickets
 */
enum EntryStatusFilter: string
{
    /** Published, with a draft */
    case UnpublishedChanges = 'unpublished_changes';

    case NeverPublished = 'never_published';

    /** Disabled, or outside its date window: it hasn't started, or it has ended */
    case DisabledOrOutsideDateWindow = 'disabled_or_outside_date_window';

    /**
     * A translation key
     */
    public function label(): string
    {
        return 'setono_sylius_cms.ui.status_filter.' . $this->value;
    }
}
