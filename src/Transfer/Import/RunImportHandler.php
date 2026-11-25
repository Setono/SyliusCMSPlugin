<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Runs an import started in the admin on the Messenger worker, from a local copy of the zip kept in the transfer
 * storage. An uploaded zip gets its dry run, as if overwriting, so the plan shows its conflicts and checks everything
 * else: one that would apply waits for an admin user to confirm it, and one that refuses even so is finished. A
 * confirmed import runs with the options it was confirmed with. Either way the result is recorded, a refusal included,
 * and the handler returns, so Messenger doesn't retry it: only an unexpected error throws, for Messenger to retry. It
 * saves that it started a run before it runs it, in a write of its own: an import still running when the handler gets
 * its message again stopped the worker, as on a fatal error such as PHP running out of memory, which skips Messenger's
 * retries, so it fails rather than running again, and the message is handled. An unexpected error has it wait for the
 * worker again first, so Messenger's retry runs it. The zip is deleted once the import finishes. See "Who runs it" in
 * docs/spec/transfer.md
 */
final readonly class RunImportHandler
{
    /**
     * @param ObjectManager $manager the import resource's manager
     */
    public function __construct(
        private ImportRepositoryInterface $imports,
        private ImporterInterface $importer,
        private ImportResultNormalizer $normalizer,
        private UploadedExports $uploadedExports,
        private ObjectManager $manager,
        private ClockInterface $clock,
        private ExportSources $sources,
    ) {
    }

    public function __invoke(RunImport $message): void
    {
        $import = $this->imports->findOneByUuid($message->importUuid);
        $path = $import?->getPath();
        if (null === $import || null === $path || !$import->isWaiting()) {
            return;
        }

        if ($import->isRunning()) {
            $this->stop($import, $path);

            return;
        }

        $state = $import->getState();
        $dryRun = ImportState::Checking === $state;
        $filename = $import->getFilename();

        // Saved before the run, in a write of its own that the run's rollback doesn't undo
        $import->start($this->clock->now());
        $this->manager->flush();

        try {
            $result = $this->run($path, $filename, $dryRun
                // As if overwriting, so the dry run checks everything else too, and the plan still lists the conflicts
                ? new ImportOptions(dryRun: true, overwrite: true)
                : new ImportOptions(publish: $import->isPublish(), overwrite: $import->isOverwrite()));
        } catch (\Throwable $e) {
            // Messenger retries it, which runs it again. The importer clears the entity manager after it rolls back
            $this->imports->findOneByUuid($message->importUuid)?->retry();
            $this->manager->flush();

            throw $e;
        }

        // The importer clears the entity manager after it rolls back, as a dry run does, and an admin user may have
        // abandoned the import while its dry run ran
        $import = $this->imports->findOneByUuid($message->importUuid);
        if (null === $import || $state !== $import->getState()) {
            return;
        }

        $this->record($import, $result, $dryRun);
    }

    private function run(string $path, string $filename, ImportOptions $options): ImportResult
    {
        if (!$this->uploadedExports->has($path)) {
            return ImportResult::invalid(sprintf('The uploaded %s is no longer in the transfer storage.', $filename));
        }

        return $this->uploadedExports->read($path, function (string $file) use ($filename, $options): ImportResult {
            try {
                $source = $this->sources->zip($file);
            } catch (InvalidExportException) {
                return ImportResult::invalid(sprintf('%s isn\'t a zip that can be read.', $filename));
            }

            return $this->importer->import($source, $options);
        });
    }

    /**
     * Fails an import the worker stopped while running, as on a fatal error such as PHP running out of memory, which
     * skips Messenger's retries, so the transport hands the message out again. Running it again could stop the worker
     * again, over and over
     */
    private function stop(ImportInterface $import, string $path): void
    {
        $import->stop($this->clock->now());
        $this->manager->flush();
        $this->uploadedExports->delete($path);
    }

    private function record(ImportInterface $import, ImportResult $result, bool $dryRun): void
    {
        if ($dryRun && ImportStatus::WouldApply === $result->status) {
            $import->check($this->normalizer->normalize($result));
            $this->manager->flush();

            return;
        }

        $path = $import->getPath();
        $import->finish($this->normalizer->normalize($result), $this->clock->now());
        $this->manager->flush();

        if (null !== $path) {
            $this->uploadedExports->delete($path);
        }
    }
}
