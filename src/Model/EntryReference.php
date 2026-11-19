<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class EntryReference implements EntryReferenceInterface
{
    protected ?int $id = null;

    /**
     * Set as soon as the row is created
     */
    protected EntryInterface $entry;

    protected string $field = '';

    protected string $uuid = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): EntryInterface
    {
        return $this->entry;
    }

    public function setEntry(EntryInterface $entry): void
    {
        $this->entry = $entry;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = strtolower($uuid);
    }
}
