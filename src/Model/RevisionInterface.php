<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * An entry's published content as it was at one publish, kept so editors can preview it and restore it into the draft.
 * Every publish stores one: the whole entry in the entry format, who published it, when, and whether it was scheduled.
 * On-save settings and placements aren't part of it. See "Revisions" in docs/spec/publishing.md
 */
interface RevisionInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function setEntry(EntryInterface $entry): void;

    /**
     * The entry in the entry format, as canonical JSON, as it was saved. Read it upgraded through Revision\RevisionReader
     */
    public function getContent(): string;

    public function setContent(string $content): void;

    /**
     * In UTC
     */
    public function getPublishedAt(): \DateTimeImmutable;

    public function setPublishedAt(\DateTimeImmutable $publishedAt): void;

    /**
     * Who published it, as the admin names editors, or null when no one is known
     */
    public function getPublishedBy(): ?string;

    public function setPublishedBy(?string $publishedBy): void;

    /**
     * Whether a scheduled publish stored it, rather than an editor's Publish
     */
    public function isScheduled(): bool;

    public function setScheduled(bool $scheduled): void;
}
