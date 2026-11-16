<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Revision implements RevisionInterface
{
    protected ?int $id = null;

    /**
     * Set as soon as the revision is created
     */
    protected EntryInterface $entry;

    /**
     * Canonical JSON, kept as text, not as a database JSON type, because MySQL's JSON type reorders keys
     */
    protected string $content = '';

    /**
     * Set as soon as the revision is created. The column holds UTC
     */
    protected \DateTimeImmutable $publishedAt;

    protected ?string $publishedBy = null;

    protected bool $scheduled = false;

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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * Doctrine hydrates the column in PHP's default time zone, but it holds UTC
     */
    public function getPublishedAt(): \DateTimeImmutable
    {
        return UtcColumn::read($this->publishedAt);
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getPublishedBy(): ?string
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(?string $publishedBy): void
    {
        $this->publishedBy = $publishedBy;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled;
    }

    public function setScheduled(bool $scheduled): void
    {
        $this->scheduled = $scheduled;
    }
}
