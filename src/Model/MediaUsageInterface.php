<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A row of the media usage index: one entry's use of one media item, in its draft, in its published content or in both,
 * or one editor preset's, in fields and in rich text. The index drives each media item's Used in list, the usage count
 * in the library, the unused filter and whether a media item can be deleted. Revisions and code presets aren't indexed
 * (ADR 0013). See "Usage index" in docs/spec/media.md
 */
interface MediaUsageInterface extends ResourceInterface
{
    public function getId(): ?int;

    /**
     * The entry that uses the media item. A row has an entry or an editor preset, never both
     */
    public function getEntry(): ?EntryInterface;

    public function setEntry(?EntryInterface $entry): void;

    /**
     * The editor preset that uses the media item. A row has an entry or an editor preset, never both
     */
    public function getPreset(): ?EditorPresetInterface;

    public function setPreset(?EditorPresetInterface $preset): void;

    /**
     * The UUID of the media item the entry refers to, lowercase, as content holds it. Content refers to media items by
     * UUID (ADR 0002), so a row can name one that was deleted, such as after a revision is restored
     */
    public function getMediaItemUuid(): string;

    public function setMediaItemUuid(string $mediaItemUuid): void;

    /**
     * Whether the entry's draft uses it: what the builder edits, which is the published content when a published entry
     * has no draft, and the rows of a never-published entry. False for an editor preset, which has no draft
     */
    public function isInDraft(): bool;

    public function setInDraft(bool $inDraft): void;

    /**
     * Whether the published content, which shoppers see, uses it. False for an editor preset, which shoppers never see
     */
    public function isInPublished(): bool;

    public function setInPublished(bool $inPublished): void;
}
