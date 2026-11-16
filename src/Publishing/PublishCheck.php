<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Steps 1 and 2 of "Publishing now" in docs/spec/publishing.md: validation refuses on errors, then a BeforeEntryPublish
 * listener can veto. A publish runs them, and so do scheduling a publish and the scheduled publish when it runs, so
 * problems show when the editor schedules and stop a publish that has them by the time it runs
 *
 * @internal
 */
final readonly class PublishCheck
{
    public function __construct(
        private EntryValidatorInterface $entryValidator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param EntryInterface $workingCopy what would be published: the draft, or the rows of an entry that has none
     * @param bool $scheduled whether it's for a scheduled publish, which listeners are told
     *
     * @throws InvalidEntryException when validation finds errors, before listeners hear of it
     * @throws PublishVetoedException when a BeforeEntryPublish listener vetoes
     */
    public function check(EntryInterface $workingCopy, bool $scheduled): void
    {
        $errors = $this->entryValidator->validate($workingCopy);
        if ([] !== $errors) {
            throw new InvalidEntryException($errors);
        }

        $veto = $this->eventDispatcher->dispatch(new BeforeEntryPublish($workingCopy, $scheduled))->getVetoMessage();
        if (null !== $veto) {
            throw new PublishVetoedException($veto);
        }
    }
}
