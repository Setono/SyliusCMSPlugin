<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Sylius\Resource\Model\ResourceInterface;

/**
 * An import started in the admin: the uploaded zip, kept in the plugin's media storage so a Messenger worker on another
 * server can read it, the dry run's result the admin user reviews, the options they confirmed it with, and the result
 * the worker recorded. The zip is let go of, and deleted, when the import finishes or is abandoned. The results are
 * Transfer\Import\ImportResult as Transfer\Import\ImportResultNormalizer writes them. See "Transfer" in
 * docs/spec/admin.md and "Who runs it" in docs/spec/transfer.md
 */
interface ImportInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getUuid(): string;

    /**
     * The name the zip was uploaded with
     */
    public function getFilename(): string;

    public function setFilename(string $filename): void;

    /**
     * Where the zip is kept in the plugin's media storage, until the import finishes or is abandoned
     */
    public function getPath(): ?string;

    public function setPath(string $path): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): void;

    public function getState(): ImportState;

    /**
     * Whether it waits for the Messenger worker: to check it, or to import it
     */
    public function isWaiting(): bool;

    /**
     * Whether it can still be abandoned: until it's confirmed, while it's checked or waits to be confirmed
     */
    public function isAbandonable(): bool;

    /**
     * When the worker started its dry run or, once it's confirmed, the import, or null while it waits for the worker
     */
    public function getStartedAt(): ?\DateTimeImmutable;

    /**
     * Records that the worker started its dry run or, once it's confirmed, the import. The worker saves it before it
     * runs it, in a write of its own, so a run that stops the worker leaves it running
     */
    public function start(\DateTimeImmutable $startedAt): void;

    /**
     * Whether the worker started its dry run or the import and hasn't recorded how that ended. A worker that gets its
     * message again while it's running stopped while running it, as on a fatal error, which skips Messenger's retries
     */
    public function isRunning(): bool;

    /**
     * Has it wait for the worker again after an unexpected error, which Messenger retries, so the retry runs it rather
     * than taking it for a run the worker stopped while running
     */
    public function retry(): void;

    /**
     * Records the dry run's result, which would apply, and waits for an admin user to confirm it
     *
     * @param array<string, mixed> $dryRun
     *
     * @throws \LogicException unless the worker is checking it
     */
    public function check(array $dryRun): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getDryRun(): ?array;

    /**
     * Has the worker import it, with these options
     *
     * @param bool $overwrite whether to overwrite the records the plan lists as conflicts
     * @param bool $publish whether to publish every entry it writes
     *
     * @throws \LogicException unless its dry run would apply and it waits to be confirmed
     */
    public function confirm(bool $overwrite, bool $publish): void;

    public function isOverwrite(): bool;

    public function isPublish(): bool;

    /**
     * Records its result, which is its dry run's when that refused, and lets go of the zip
     *
     * @param array<string, mixed> $result
     *
     * @throws \LogicException unless the worker is checking or importing it
     */
    public function finish(array $result, \DateTimeImmutable $finishedAt): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array;

    /**
     * Lets go of the zip of an import no one confirmed
     *
     * @throws \LogicException once it's confirmed or done
     */
    public function abandon(\DateTimeImmutable $abandonedAt): void;

    /**
     * When it finished, or was abandoned
     */
    public function getFinishedAt(): ?\DateTimeImmutable;

    /**
     * Records the unexpected error the worker kept failing to check or import it with, once Messenger gave up retrying,
     * and lets go of the zip, which the caller deletes
     *
     * @throws \LogicException unless the worker checks or imports it
     */
    public function fail(string $reason, \DateTimeImmutable $failedAt): void;

    /**
     * Fails it because the worker stopped while running it, as on a fatal error such as PHP running out of memory,
     * which leaves no error to record, so it isn't run again, and lets go of the zip, which the caller deletes
     *
     * @throws \LogicException unless it's running
     */
    public function stop(\DateTimeImmutable $stoppedAt): void;

    /**
     * Whether it failed because the worker stopped while running it
     */
    public function isStopped(): bool;

    /**
     * The error it failed with, once it's failed, or null when the worker stopped while running it
     */
    public function getFailure(): ?string;
}
