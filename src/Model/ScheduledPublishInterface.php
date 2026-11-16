<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A publish of an entry's draft, or of a never-published entry's rows, set for a chosen time. An entry has at most one.
 * While it waits, the draft is locked. The Messenger worker runs it, with a delayed message that carries its token, so
 * the message of one that was cancelled or replaced does nothing. One that was stopped when it ran stays, flagged as
 * failed with the reason, until it's scheduled again or cancelled. See "Scheduled publishes" in docs/spec/publishing.md
 */
interface ScheduledPublishInterface extends ResourceInterface
{
    /**
     * How long after its time a scheduled publish that hasn't run is overdue, when the admin warns that the Messenger
     * worker may not be running, as a date modifier
     */
    public const OVERDUE_AFTER = '+5 minutes';

    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function setEntry(EntryInterface $entry): void;

    /**
     * Sets it for a time, by an editor, as waiting, with a new token. A failed one is no longer flagged
     *
     * @param string|null $scheduledBy the editor's name, as the admin names editors, or null when no one is known
     */
    public function schedule(\DateTimeImmutable $publishAt, ?string $scheduledBy): void;

    /**
     * What the message that runs it carries. It's new each time it's scheduled
     */
    public function getToken(): string;

    /**
     * When it publishes, in UTC
     */
    public function getPublishAt(): \DateTimeImmutable;

    /**
     * Who scheduled it, as the admin names editors, or null when no one is known. Its revision names them as who published it
     */
    public function getScheduledBy(): ?string;

    /**
     * Flags it as stopped when it ran, by a validation error or a veto. It's no longer waiting, so the draft unlocks
     */
    public function fail(string $reason): void;

    public function isFailed(): bool;

    /**
     * Why it was stopped when it ran, or null when it wasn't
     */
    public function getFailureReason(): ?string;

    /**
     * Whether it's waiting OVERDUE_AFTER after its time. A failed one isn't
     */
    public function isOverdue(\DateTimeImmutable $now): bool;
}
