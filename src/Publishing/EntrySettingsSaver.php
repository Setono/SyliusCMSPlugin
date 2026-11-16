<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Saves the on-save settings onto the entry's rows. It never reads or writes the draft, so changing a setting never
 * publishes someone's unfinished changes. See "What takes effect on save" in docs/spec/publishing.md
 */
final readonly class EntrySettingsSaver implements EntrySettingsSaverInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private EditRecorder $editRecorder,
    ) {
    }

    public function save(EntryInterface $entry, EntrySettings $settings): void
    {
        $manager = EntityManagers::of($this->managerRegistry, $entry::class);

        $changed = $settings->changesFrom(EntrySettings::of($entry));
        if ([] === $changed) {
            return;
        }

        $manager->wrapInTransaction(function () use ($entry, $settings): void {
            $settings->applyTo($entry);
            $entry->setChangedAt($this->clock->now()->setTimezone(new \DateTimeZone('UTC')));
            $this->editRecorder->record($entry);
        });

        // After the transaction commits
        $this->eventDispatcher->dispatch(new EntrySettingsChanged($entry->getUuid(), $changed));
    }
}
