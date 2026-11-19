<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\ImportFinished;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Reads the export, plans, and applies the plan in one database transaction: any conflict, refusal or error means
 * nothing changes. A dry run applies it too, in a transaction it rolls back, so it checks everything the real run
 * would, publish validation included, and exits as the real run would. The events of the services the import writes
 * through wait until the transaction commits, and are forgotten when it rolls back. Once an import commits, it
 * dispatches ImportFinished with the result. After a rollback it clears the entity manager, whose entities no longer
 * match the database. See "What an import does" in docs/spec/transfer.md
 */
final readonly class Importer implements ImporterInterface
{
    public function __construct(
        private ExportReader $reader,
        private ImportPlannerInterface $planner,
        private ImportApplier $applier,
        private EntityManagerInterface $manager,
        private AfterCommitDispatcher $events,
    ) {
    }

    public function import(ExportSourceInterface $source, ImportOptions $options): ImportResult
    {
        try {
            $export = $this->reader->read($source);
        } catch (InvalidExportException $e) {
            return ImportResult::invalid($e->getMessage());
        }

        $plan = $this->planner->plan($export);
        if ($plan->isRefused($options->overwrite)) {
            return ImportResult::refused($plan);
        }

        $connection = $this->manager->getConnection();
        $this->events->hold();
        $connection->beginTransaction();

        try {
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
            $connection->commit();
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        $this->events->release();

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
        $this->manager->clear();
    }
}
