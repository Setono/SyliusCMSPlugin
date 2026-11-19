<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A preset an editor saved in the builder, stored in the preset file format with its UUID and changedAt. Presets
 * aren't entries, so they have no drafts or revisions: saving one takes effect at once. Read and write it as a
 * Preset\Preset, through Preset\PresetProviderInterface and Preset\EditorPresetSaverInterface. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
interface EditorPresetInterface extends ResourceInterface
{
    public function getId(): ?int;

    /**
     * Names it everywhere, in the builder, in exports and in imports, which give it the UUID it has in the export
     */
    public function getUuid(): string;

    public function setUuid(string $uuid): void;

    /**
     * The preset in the preset file format, as canonical JSON, with this UUID and changedAt
     */
    public function getContent(): string;

    public function setContent(string $content): void;

    public function getCreatedAt(): \DateTimeImmutable;

    /**
     * When it last changed, in UTC: when it was saved, replaced, renamed or recategorised, or the export's when it was
     * imported. See "Stale records" in docs/spec/transfer.md
     */
    public function getChangedAt(): \DateTimeImmutable;

    public function setChangedAt(\DateTimeImmutable $changedAt): void;
}
