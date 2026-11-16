<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Draft implements DraftInterface
{
    protected ?int $id = null;

    /**
     * Every content type extends Entry, the root of the entries' single table inheritance (ADR 0001). Set as soon as
     * the draft is created
     */
    protected Entry $entry;

    /**
     * Canonical JSON, kept as text, not as a database JSON type, because MySQL's JSON type reorders keys
     */
    protected string $content = '';

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
        if (!$entry instanceof Entry) {
            throw new \InvalidArgumentException(sprintf('A draft belongs to an entry, which extends %s', Entry::class));
        }

        $this->entry = $entry;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
