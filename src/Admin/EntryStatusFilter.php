<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * The statuses an entry list filters on, in the order the filter offers them. See "Entry lists" in docs/spec/admin.md
 */
enum EntryStatusFilter: string
{
    /** With an error or a warning. See "Problems" in docs/spec/admin.md */
    case NeedsAttention = 'needs_attention';

    /** Published, with a draft */
    case UnpublishedChanges = 'unpublished_changes';

    case NeverPublished = 'never_published';

    /** With a scheduled publish that waits, overdue or not. A failed one needs attention instead */
    case Scheduled = 'scheduled';

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
