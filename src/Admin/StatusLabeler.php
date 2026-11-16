<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;

final readonly class StatusLabeler implements StatusLabelerInterface
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function labelsOf(EntryInterface $entry, bool $hasDraft, ?ScheduledPublishInterface $scheduledPublish = null): array
    {
        $labels = match (EntryStatus::of($entry, $hasDraft)) {
            EntryStatus::NEVER_PUBLISHED => [new StatusLabel(StatusLabel::NEVER_PUBLISHED)],
            EntryStatus::UNPUBLISHED_CHANGES => [new StatusLabel(StatusLabel::PUBLISHED), new StatusLabel(StatusLabel::UNPUBLISHED_CHANGES)],
            default => [new StatusLabel(StatusLabel::PUBLISHED)],
        };

        // The scheduled publish's state is a label of the same code
        $scheduled = EntryStatus::ofScheduledPublish($scheduledPublish, $this->clock->now());
        if (null !== $scheduledPublish && null !== $scheduled) {
            $labels[] = new StatusLabel($scheduled, ['at' => $scheduledPublish->getPublishAt()] + ($scheduledPublish->isFailed() ? ['reason' => $scheduledPublish->getFailureReason()] : []));
        }

        if (!$entry->isEnabled()) {
            $labels[] = new StatusLabel(StatusLabel::DISABLED);
        }

        // "Show until" is exclusive, so a window has ended once it's reached
        $window = $entry->getDateWindow();
        if (null !== $window->until && $window->until <= $this->clock->now()) {
            $labels[] = new StatusLabel(StatusLabel::ENDED, ['until' => $window->until]);
        } elseif (null !== $window->from || null !== $window->until) {
            $labels[] = new StatusLabel(StatusLabel::DATE_WINDOW, ['from' => $window->from, 'until' => $window->until]);
        }

        $audience = $entry->getAudience();
        if (AudienceMode::Everyone !== $audience->mode) {
            $labels[] = new StatusLabel(StatusLabel::AUDIENCE, ['mode' => $audience->mode->value, 'groups' => $audience->groups]);
        }

        return $labels;
    }
}
