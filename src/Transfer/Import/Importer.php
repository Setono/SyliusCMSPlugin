<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\AfterCommitMessageBus;
use Setono\SyliusCMSPlugin\Event\ImportFinished;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads the export, plans, and applies the plan in one database transaction: any conflict, refusal or error means
 * nothing changes. A zip that unpacks to more than it may refuses the import before anything is planned. A dry run applies it too, in a transaction it rolls back, so it checks everything the real run
 * would, publish validation included, and exits as the real run would. Media come first, then the entries and presets
 * that use them. The media files are written to storage only once the plan is complete, right before the commit, and
 * removed again when it can't commit, so an import that refuses or rolls back writes none. The events and messages of
 * what the import writes, such as a replaced file's delayed delete, wait until the transaction commits, and are
 * forgotten when it rolls back. Once an import commits, it dispatches ImportFinished with the result. After a rollback
 * it clears the entity manager, whose entities no longer match the database. See "What an import does" in
 * docs/spec/transfer.md
 */
final readonly class Importer implements ImporterInterface
{
    /**
     * @param FilesystemOperator $storage the plugin's media storage
     */
    public function __construct(
        private ExportReader $reader,
        private ImportPlannerInterface $planner,
        private MediaApplier $mediaApplier,
        private ImportApplier $applier,
        private EntityManagerInterface $manager,
        private AfterCommitDispatcher $events,
        private AfterCommitMessageBus $messages,
        private FilesystemOperator $storage,
    ) {
    }

    public function import(ExportSourceInterface $source, ImportOptions $options): ImportResult
    {
        try {
            $export = $this->reader->read($source);
        } catch (InvalidExportException $e) {
            return ImportResult::invalid($e->getMessage());
        } catch (ExportTooBigException $e) {
            return ImportResult::refused(new ImportPlan([], refusals: [new ImportRefusal(null, $e->reason)]));
        }

        try {
            return $this->importRead($export, $options);
        } finally {
            $export->cleanUp();
        }
    }

    private function importRead(ReadExport $export, ImportOptions $options): ImportResult
    {
        $plan = $this->planner->plan($export);
        if ($plan->isRefused($options->overwrite)) {
            return ImportResult::refused($plan);
        }

        $connection = $this->manager->getConnection();
        $this->events->hold();
        $this->messages->hold();
        $connection->beginTransaction();

        try {
            $files = $this->mediaApplier->apply($export, $plan);
            $refusals = $this->applier->apply($export, $plan, $options);
            $this->manager->flush();
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        if ([] !== $refusals || $options->dryRun) {
            $this->rollBack();

            return [] === $refusals ? ImportResult::applied($plan, true) : ImportResult::refused($plan->withRefusals($refusals));
        }

        try {
            // The plan is complete, so the files go to storage, before the commit makes the media items point at them
            $files->store($this->storage);
            $connection->commit();
        } catch (\Throwable $e) {
            $this->rollBack();
            $files->remove($this->storage);

            throw $e;
        }

        $this->events->release();
        $this->messages->release();

        $result = ImportResult::applied($plan, false);
        $this->events->dispatch(new ImportFinished($result));

        return $result;
    }

    private function rollBack(): void
    {
        $connection = $this->manager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        $this->events->forget();
        $this->messages->forget();
        $this->manager->clear();
    }
}
