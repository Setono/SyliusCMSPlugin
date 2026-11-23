<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishInThePastException;
use Setono\SyliusCMSPlugin\Publishing\SchedulingTimeZone;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Schedule, and Schedule again for a failed scheduled publish, which schedules a publish as the
 * builder's does. The form's "at" is a datetime-local input's value, such as 2026-11-27T09:00, in the scheduling time
 * zone (SchedulingTimeZone), the one the builder converts its times from. See "Entry overview page" in
 * docs/spec/admin.md and "Scheduled publishes" in docs/spec/publishing.md
 */
final readonly class ScheduleEntryAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private PublishSchedulerInterface $scheduler,
        private SchedulingTimeZone $timeZone,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($request): array {
            $at = $this->timeZone->parse((string) $request->request->get('at'));
            if (null === $at) {
                return ['error', 'setono_sylius_cms.entry_schedule_time_missing'];
            }

            try {
                $this->scheduler->schedule($entry, $at);
            } catch (ScheduledPublishInThePastException) {
                return ['error', 'setono_sylius_cms.entry_schedule_in_the_past'];
            } catch (InvalidEntryException $e) {
                return ['error', ['message' => 'setono_sylius_cms.entry_schedule_invalid', 'parameters' => ['%count%' => count($e->errors)]]];
            } catch (PublishVetoedException $e) {
                return ['error', $e->getMessage()];
            }

            return ['success', ['message' => 'setono_sylius_cms.entry_publish_scheduled', 'parameters' => ['%at%' => $this->timeZone->format($at), '%time_zone%' => $this->timeZone->name()]]];
        });
    }
}
