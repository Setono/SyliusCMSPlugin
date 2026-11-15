<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class EntryPresence implements EntryPresenceInterface
{
    protected ?int $id = null;

    /**
     * @param string $editor the admin user's identifier
     * @param string $name the admin user's name, as the builder shows it
     */
    public function __construct(
        protected Entry $entry,
        protected string $editor,
        protected string $name,
        protected \DateTimeImmutable $seenAt,
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

    public function getEditor(): string
    {
        return $this->editor;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function see(string $name, \DateTimeImmutable $seenAt): void
    {
        $this->name = $name;
        $this->seenAt = $seenAt;
    }
}
