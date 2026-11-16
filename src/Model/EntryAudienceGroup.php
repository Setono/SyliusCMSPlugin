<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class EntryAudienceGroup implements EntryAudienceGroupInterface
{
    protected ?int $id = null;

    public function __construct(
        protected EntryInterface $entry,
        protected string $code,
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

    public function getCode(): string
    {
        return $this->code;
    }
}
