<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * One channel an entry is in, by the channel's code (ADR 0002), as a row beside the entry so the visible-entries query
 * and the URL lookup filter on it in SQL. Read and change an entry's channels through EntryInterface
 *
 * @internal
 */
class EntryChannel
{
    protected ?int $id = null;

    public function __construct(
        protected Entry $entry,
        protected string $code,
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

    public function getCode(): string
    {
        return $this->code;
    }
}
