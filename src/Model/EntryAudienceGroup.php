<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class EntryAudienceGroup implements EntryAudienceGroupInterface
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
