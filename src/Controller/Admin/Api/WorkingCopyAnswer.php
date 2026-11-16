<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

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
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * What the builder loads an entry from: the working copy in the entry format, with the lock token a save made on it
 * carries, the entry's on-save settings, which aren't part of the draft, its state, and who else has it open.
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
    ) {
    }

    public function __invoke(EntryInterface $entry, LoadedWorkingCopy $loaded): JsonResponse
    {
        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'entry' => $this->entryNormalizer->normalize($loaded->workingCopy),
            'lockToken' => $loaded->lockToken,
            'settings' => $this->settingsNormalizer->normalize(EntrySettings::of($entry)),
            'status' => EntryStatus::of($entry, $this->workingCopies->hasDraft($entry)),
            'editors' => self::editors($this->presence->others($entry, $this->builderRequest->admin())),
        ])), json: true);
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
