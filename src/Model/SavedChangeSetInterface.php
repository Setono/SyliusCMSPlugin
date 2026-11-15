<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * One save of an entry's working copy: the change set the server applied, numbered by the lock token the working copy
 * has after it, which the builder then carries, so the server can tell what colleagues saved since the builder last
 * loaded and merge onto it. See "Saving" in docs/spec/publishing.md. Old ones are deleted as later saves
 * come in
 */
interface SavedChangeSetInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    /**
     * The working copy's lock token after this save: the number of saves of the entry so far
     */
    public function getLockToken(): int;

    /**
     * The change set as canonical JSON
     */
    public function getChanges(): string;

    public function getSavedAt(): \DateTimeImmutable;
}
