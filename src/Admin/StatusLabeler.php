<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;

final readonly class StatusLabeler implements StatusLabelerInterface
{
    public function __construct(
        private ClockInterface $clock,
        private Availability $availability,
    ) {
    }

    public function labelsOf(EntryInterface $entry, bool $hasDraft, ?ScheduledPublishInterface $scheduledPublish = null): array
    {
        $labels = match (EntryStatus::of($entry, $hasDraft)) {
            EntryStatus::NEVER_PUBLISHED => [new StatusLabel(StatusLabel::NEVER_PUBLISHED)],
            EntryStatus::UNPUBLISHED_CHANGES => [new StatusLabel(StatusLabel::PUBLISHED), new StatusLabel(StatusLabel::UNPUBLISHED_CHANGES)],
            default => [new StatusLabel(StatusLabel::PUBLISHED)],
        };

        // The scheduled publish's state is a label of the same code
        if (null !== $scheduledPublish) {
            $labels[] = new StatusLabel(
                EntryStatus::ofScheduledPublish($scheduledPublish, $this->clock->now()),
                ['at' => $scheduledPublish->getPublishAt()] + ($scheduledPublish->isFailed() ? ['reason' => $scheduledPublish->getFailureReason()] : []),
            );
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

        $heldBack = $this->heldBack($entry);
        if ([] !== $heldBack) {
            $labels[] = new StatusLabel(StatusLabel::HELD_BACK, ['locales' => $heldBack]);
        }

        $audience = $entry->getAudience();
        if (AudienceMode::Everyone !== $audience->mode) {
            $labels[] = new StatusLabel(StatusLabel::AUDIENCE, ['mode' => $audience->mode->value, 'groups' => $audience->groups]);
        }

        return $labels;
    }

    /**
     * The locales of the entry's translations that are held back. One without a title is no translation, so it isn't
     *
     * @return list<string> in code order
     */
    private function heldBack(EntryInterface $entry): array
    {
        $localeCodes = [];
        foreach ($entry->getTranslations()->getKeys() as $localeCode) {
            $localeCode = (string) $localeCode;
            if ($this->availability->hasTranslation($entry, $localeCode) && !$this->availability->showsIn($entry, $localeCode)) {
                $localeCodes[] = $localeCode;
            }
        }
        sort($localeCodes);

        return $localeCodes;
    }
}
