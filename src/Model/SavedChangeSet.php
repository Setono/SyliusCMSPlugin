<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class SavedChangeSet implements SavedChangeSetInterface
{
    protected ?int $id = null;

    /**
     * @param string $changes the change set as canonical JSON
     */
    public function __construct(
        protected EntryInterface $entry,
        protected int $lockToken,
        protected string $changes,
        protected \DateTimeImmutable $savedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): EntryInterface
    {
        return $this->entry;
    }

    public function getLockToken(): int
    {
        return $this->lockToken;
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
