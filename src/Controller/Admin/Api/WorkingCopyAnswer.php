<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Builder\PresenceTrackerInterface;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsNormalizer;
use Setono\SyliusCMSPlugin\Publishing\LoadedWorkingCopy;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * What the builder loads an entry from: the working copy in the entry format, with the lock token a save made on it
 * carries, the entry's on-save settings, which aren't part of the draft, its state, its scheduled publish, which locks
 * the working copy while it waits, who else has it open, and the untranslated and outdated counts of each locale it has
 * a translation in.
 * entry_show answers with it, and so does entry_discard, with the published content
 *
 * @internal
 */
final readonly class WorkingCopyAnswer
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyManagerInterface $workingCopies,
        private EntryNormalizer $entryNormalizer,
        private EntrySettingsNormalizer $settingsNormalizer,
        private PresenceTrackerInterface $presence,
        private TranslatableValues $translatableValues,
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EntryInterface $entry, LoadedWorkingCopy $loaded): JsonResponse
    {
        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'entry' => $this->entryNormalizer->normalize($loaded->workingCopy),
            'lockToken' => $loaded->lockToken,
            'settings' => $this->settingsNormalizer->normalize(EntrySettings::of($entry)),
            'status' => EntryStatus::of($entry, $this->workingCopies->hasDraft($entry)),
            'schedule' => $this->schedule($entry),
            'editors' => self::editors($this->presence->others($entry, $this->builderRequest->admin())),
            'completeness' => $this->completeness($loaded->workingCopy),
        ])), json: true);
    }

    /**
     * The entry's scheduled publish, or null when it has none: when it publishes, in UTC, who scheduled it, its state
     * (publishes_at_a_time, scheduled_publish_overdue or scheduled_publish_failed) and why it failed, or null
     */
    private function schedule(EntryInterface $entry): ?ListedObject
    {
        $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
        if (null === $scheduledPublish) {
            return null;
        }

        return new ListedObject([
            'at' => $scheduledPublish->getPublishAt()->format('Y-m-d\TH:i:s\Z'),
            'by' => $scheduledPublish->getScheduledBy(),
            'status' => EntryStatus::ofScheduledPublish($scheduledPublish, $this->clock->now()),
            'reason' => $scheduledPublish->getFailureReason(),
        ]);
    }

    /**
     * The untranslated and outdated counts of each locale the working copy has a translation in: one with a title.
     * See "Completeness and outdated flags" in docs/spec/localization.md
     */
    private function completeness(EntryInterface $workingCopy): \stdClass
    {
        $completeness = [];
        foreach ($workingCopy->getTranslations() as $localeCode => $translation) {
            if (null === $translation->getTitle() || '' === $translation->getTitle()) {
                continue;
            }

            $counts = $this->translatableValues->completeness($workingCopy, (string) $localeCode);
            $completeness[$counts->localeCode] = new ListedObject(['untranslated' => $counts->untranslated, 'outdated' => $counts->outdated]);
        }

        return (object) $completeness;
    }

    /**
     * Who else has the entry open, as the builder shows them
     *
     * @param list<string> $names
     *
     * @return list<ListedObject>
     */
    public static function editors(array $names): array
    {
        return array_map(static fn (string $name): ListedObject => new ListedObject(['name' => $name]), $names);
    }
}
