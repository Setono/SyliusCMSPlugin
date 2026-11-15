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
