<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A published entry's saved changes, which shoppers don't see until they're published. An entry has at most one,
 * shared by everyone who edits it. It's the whole entry in the entry format, beside the rows that hold the published
 * content (ADR 0009). See "Drafts" in docs/spec/publishing.md
 */
interface DraftInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function setEntry(EntryInterface $entry): void;

    /**
     * The entry in the entry format, as canonical JSON
     */
    public function getContent(): string;

    public function setContent(string $content): void;
}
