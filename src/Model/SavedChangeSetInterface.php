<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * One save of an entry's working copy: the change set the server applied, numbered by the working copy's version after
 * it. That number is the lock token the builder carries, so the server can tell what colleagues saved since the
 * builder last loaded and merge onto it. See "Saving" in docs/spec/publishing.md. Old ones are deleted as later saves
 * come in
 */
interface SavedChangeSetInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function getVersion(): int;

    /**
     * The change set as canonical JSON
     */
    public function getChanges(): string;

    public function getSavedAt(): \DateTimeImmutable;
}
