<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * One save of an entry's working copy: the change set the server applied, numbered by the working copy's version after
 * it. That number is the lock token the builder carries, so the server can tell what colleagues saved since the
 * builder last loaded and merge onto it. See "Saving" in docs/spec/publishing.md. Old ones are deleted as later saves
 * come in
 *
 * @internal
 */
class SavedChangeSet
{
    protected ?int $id = null;

    /**
     * @param string $changes the change set as canonical JSON
     */
    public function __construct(
        protected Entry $entry,
        protected int $version,
        protected string $changes,
        protected \DateTimeImmutable $savedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getChanges(): string
    {
        return $this->changes;
    }

    public function getSavedAt(): \DateTimeImmutable
    {
        return $this->savedAt;
    }
}
