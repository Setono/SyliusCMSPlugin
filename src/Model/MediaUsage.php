<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class MediaUsage implements MediaUsageInterface
{
    protected ?int $id = null;

    /**
     * Set as soon as the row is created
     */
    protected EntryInterface $entry;

    protected string $mediaItemUuid = '';

    protected bool $inDraft = false;

    protected bool $inPublished = false;

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

    public function getMediaItemUuid(): string
    {
        return $this->mediaItemUuid;
    }

    public function setMediaItemUuid(string $mediaItemUuid): void
    {
        $this->mediaItemUuid = $mediaItemUuid;
    }

    public function isInDraft(): bool
    {
        return $this->inDraft;
    }

    public function setInDraft(bool $inDraft): void
    {
        $this->inDraft = $inDraft;
    }

    public function isInPublished(): bool
    {
        return $this->inPublished;
    }

    public function setInPublished(bool $inPublished): void
    {
        $this->inPublished = $inPublished;
    }
}
