<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslatableInterface;

/**
 * A single piece of content of one content type. See "Entries" in docs/spec/content-model.md
 *
 * @method EntryTranslationInterface getTranslation(?string $locale = null)
 */
interface EntryInterface extends ResourceInterface, TranslatableInterface
{
    public function getId(): ?int;

    /**
     * Its identity everywhere: references, transfer, URLs in the admin
     */
    public function getUuid(): string;

    /**
     * When it was created, in UTC. Set once
     */
    public function getCreatedAt(): \DateTimeImmutable;

    /**
     * When its published content or an on-save setting last changed
     */
    public function getChangedAt(): \DateTimeImmutable;

    public function getLastPublishedAt(): ?\DateTimeImmutable;

    public function isPublished(): bool;

    /**
     * Marks the entry published at $at. Until drafts arrive, saves write straight to the rows,
     * so publishing only records that the rows are now live
     */
    public function publish(\DateTimeImmutable $at): void;

    /**
     * One block tree for all translations
     */
    public function getBlockTree(): StoredTree;

    public function setBlockTree(StoredTree $tree): void;

    /**
     * The block tree as it's stored: canonical JSON
     */
    public function getBlockTreeJson(): string;

    /**
     * The title in the current locale
     */
    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    /**
     * The slug in the current locale, for content types with their own URL
     */
    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;
}
