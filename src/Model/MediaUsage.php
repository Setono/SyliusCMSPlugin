<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class MediaUsage implements MediaUsageInterface
{
    protected ?int $id = null;

    /**
     * The entry that uses the media item, or null for a row of an editor preset
     */
    protected ?EntryInterface $entry = null;

    /**
     * The editor preset that uses the media item, or null for a row of an entry
     */
    protected ?EditorPresetInterface $preset = null;

    protected string $mediaItemUuid = '';

    protected bool $inDraft = false;

    protected bool $inPublished = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): ?EntryInterface
    {
        return $this->entry;
    }

    public function setEntry(?EntryInterface $entry): void
    {
        $this->entry = $entry;
    }

    public function getPreset(): ?EditorPresetInterface
    {
        return $this->preset;
    }

    public function setPreset(?EditorPresetInterface $preset): void
    {
        $this->preset = $preset;
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
