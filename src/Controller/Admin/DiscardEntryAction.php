<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Discard changes, which discards the draft as the builder's does, so builders that have the entry
 * open pull the published content in. It's refused while a scheduled publish locks the draft. See
 * "Entry overview page" in docs/spec/admin.md
 */
final readonly class DiscardEntryAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private AutosaveInterface $autosave,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry): array {
            try {
                $this->autosave->discard($entry);
            } catch (DraftLockedException) {
                return ['error', 'setono_sylius_cms.entry_draft_locked'];
            }

            return ['success', 'setono_sylius_cms.entry_changes_discarded'];
        });
    }
}
