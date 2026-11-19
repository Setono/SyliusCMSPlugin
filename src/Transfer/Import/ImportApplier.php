<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaverInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Step 3 of an import: it writes what the plan says, inside the import's one transaction, through the services an
 * editor's saves go through, so each write is what a save in the admin would be. A picked entry's content becomes its
 * draft, or its rows when it was never published in the target. Its on-save settings and a snippet's placements apply
 * at once. Every write sets changedAt to the export's value. With --publish, every entry it wrote is validated first, all
 * of them, and then published, the snippets an entry shows before it. See "What an import does" in
 * docs/spec/transfer.md
 *
 * #280 writes the media folders and items first, with their files, which are stored once the plan is complete
 *
 * @internal
 */
final readonly class ImportApplier
{
    /**
     * @param ContainerInterface $entryFactories the content types' resource factories, keyed by content type code
     */
    public function __construct(
        private EntityManagerInterface $manager,
        private EntryRepositoryInterface $entries,
        private ContainerInterface $entryFactories,
        private EntryDenormalizer $entryDenormalizer,
        private EditRecorder $editRecorder,
        private AutosaveInterface $autosave,
        private WorkingCopyManagerInterface $workingCopies,
        private PublishSchedulerInterface $publishScheduler,
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private EntrySettingsSaverInterface $settingsSaver,
        private ImportedPlacements $placements,
        private EditorPresetSaverInterface $presetSaver,
        private EntryValidatorInterface $validator,
        private EntryPublisherInterface $publisher,
        private TargetRecords $targetRecords,
        private AfterCommitDispatcher $events,
    ) {
    }

    /**
     * @return list<ImportRefusal> why it couldn't apply the plan, such as publishes that fail, which make the import
     *                             roll back
     */
    public function apply(ReadExport $export, ImportPlan $plan, ImportOptions $options): array
    {
        foreach ($plan->presets as $planned) {
            if (ImportAction::Create === $planned->action || ImportAction::Update === $planned->action) {
                $preset = $export->presets[$planned->uuid];
                $this->presetSaver->save($preset->preset, $preset->changedAt());
            }
        }

        /** @var list<array{ReadEntry, EntryInterface}> $written */
        $written = [];
        foreach ($plan->writtenEntries() as $planned) {
            $entry = $export->entries[$planned->uuid];
            $row = ImportAction::Create === $planned->action ? $this->create($entry) : $this->update($entry, $options->overwrite);

            try {
                $this->settingsSaver->save($row, $entry->exported->settings);
            } catch (InvalidEntrySettingsException $e) {
                return [new ImportRefusal($entry->uuid(), new TranslatableMessage('setono_sylius_cms.import.settings_refused', ['%title%' => $entry->label(), '%reason%' => $e->getMessage()]))];
            }

            if ($row instanceof SnippetInterface) {
                $this->placements->replace($row, $entry->exported->placements);
            }

            $row->setChangedAt($entry->exported->changedAt);
            $this->manager->flush();

            $written[] = [$entry, $row];
        }

        return $options->publish ? $this->publish($written) : [];
    }

    /**
     * A never-published entry of the export's content type, through its resource factory as any entry is created,
     * with the export's UUID, createdAt and content in its rows
     */
    private function create(ReadEntry $entry): EntryInterface
    {
        /** @var FactoryInterface<EntryInterface> $factory */
        $factory = $this->entryFactories->get($entry->contentType->code);
        $row = $factory->createNew();
        $row->setUuid($entry->uuid());
        $row->setCreatedAt($entry->content->getCreatedAt());

        $localeCode = $entry->content->getTranslations()->getKeys()[0] ?? null;
        if (null !== $localeCode) {
            $row->setCurrentLocale((string) $localeCode);
            $row->setFallbackLocale((string) $localeCode);
        }

        $this->entryDenormalizer->denormalize($entry->data(), $row);
        $this->editRecorder->record($row);

        $this->manager->persist($row);
        $this->manager->flush();

        $this->events->dispatch(new EntryCreated($row->getUuid(), $entry->contentType->code));

        return $row;
    }

    /**
     * The entry with the export's content in its draft, or in its rows when it was never published, unless it has that
     * content already. Overwriting cancels its scheduled publish and discards a draft the import doesn't replace
     */
    private function update(ReadEntry $entry, bool $overwrite): EntryInterface
    {
        $row = $this->entries->findOneByUuid($entry->uuid()) ?? throw new \LogicException(sprintf('The plan updates the entry %s, which there\'s none of', $entry->uuid()));

        if ($overwrite && null !== $this->scheduledPublishes->findOneByEntry($row)) {
            $this->publishScheduler->cancel($row);
        }

        if (!$this->targetRecords->hasContentOf($row, $entry)) {
            $this->autosave->replace($row, $entry->data());
        } elseif ($this->workingCopies->hasDraft($row)) {
            $this->autosave->discard($row);
        }

        return $row;
    }

    /**
     * Validates every entry first, so the plan lists each that fails, and then publishes them, the snippets an entry
     * shows before it. A publish sets changedAt to the export's value too
     *
     * @param list<array{ReadEntry, EntryInterface}> $written
     *
     * @return list<ImportRefusal>
     */
    private function publish(array $written): array
    {
        $refusals = [];
        foreach ($written as [$entry, $row]) {
            $errors = $this->validator->validate($row, $this->workingCopies->getWorkingCopy($row));
            if ([] !== $errors) {
                $refusals[] = self::notPublishable($entry, $errors);
            }
        }

        if ([] !== $refusals) {
            return $refusals;
        }

        foreach ($written as [$entry, $row]) {
            try {
                $this->publisher->publish($row);
            } catch (InvalidEntryException $e) {
                return [self::notPublishable($entry, $e->errors)];
            } catch (PublishVetoedException $e) {
                return [new ImportRefusal($entry->uuid(), new TranslatableMessage('setono_sylius_cms.import.not_publishable', ['%title%' => $entry->label(), '%reason%' => $e->getMessage()]))];
            }

            $row->setChangedAt($entry->exported->changedAt);
            $this->manager->flush();
        }

        return [];
    }

    /**
     * @param list<ValidationError> $errors
     */
    private static function notPublishable(ReadEntry $entry, array $errors): ImportRefusal
    {
        return new ImportRefusal($entry->uuid(), new TranslatableMessage('setono_sylius_cms.import.not_publishable', [
            '%title%' => $entry->label(),
            '%reason%' => implode(' ', array_unique(array_map(static fn (ValidationError $error): string => $error->message, $errors))),
        ]));
    }
}
