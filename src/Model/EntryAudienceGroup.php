<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * One customer group value in an entry's audience: a customer group code (ADR 0002), "@guest" or "@no_group", as a row
 * beside the entry so the visible-entries query filters on it in SQL (ADR 0010). Read and change an entry's audience
 * through EntryInterface
 *
 * @internal
 */
class EntryAudienceGroup
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
