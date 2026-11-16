<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Cancel the schedule, which cancels the scheduled publish as the builder's does and unlocks the
 * draft, and its Dismiss, which clears a failed one. See "Entry overview page" in docs/spec/admin.md
 */
final readonly class CancelScheduleAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private PublishSchedulerInterface $scheduler,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry): array {
            $this->scheduler->cancel($entry);

            return ['success', 'setono_sylius_cms.entry_schedule_cancelled'];
        });
    }
}
