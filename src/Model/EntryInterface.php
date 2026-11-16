<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactoryInterface;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslatableInterface;

/**
 * A single piece of content of one content type. See "Entries" in docs/spec/content-model.md
 *
 * @method EntryTranslationInterface getTranslation(?string $locale = null)
 * @method Collection<string, EntryTranslationInterface> getTranslations() indexed by locale
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

    /**
     * Saving an on-save setting bumps it, as publishing does
     */
    public function setChangedAt(\DateTimeImmutable $changedAt): void;

    public function getLastPublishedAt(): ?\DateTimeImmutable;

    /**
     * When it was last saved, in UTC: created, autosaved, discarded or its on-save settings saved. The admin shows it as "Last edited"
     */
    public function getLastEditedAt(): \DateTimeImmutable;

    /**
     * The name of the admin user who saved it last, or null when no one it knows did, such as for an entry made in code
     */
    public function getLastEditedBy(): ?string;

    public function setLastEdited(\DateTimeImmutable $at, ?string $by): void;

    /**
     * On for a new entry. Turning it off unpublishes the entry and keeps its published content. An on-save setting
     */
    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    /**
     * "Show from" (inclusive) and "Show until" (exclusive). An on-save setting
     */
    public function getDateWindow(): DateWindow;

    public function setDateWindow(DateWindow $dateWindow): void;

    /**
     * The codes of the channels it's in, sorted. A new entry starts in every channel. An on-save setting
     *
     * @return list<string>
     */
    public function getChannelCodes(): array;

    /**
     * Keeps the rows of the codes that stay and creates the rows of new ones through $factory. Change an entry's
     * channels through ChannelsAndAudienceModifierInterface, which passes the entry_channel resource's factory
     *
     * @param iterable<string> $codes
     */
    public function setChannelCodes(iterable $codes, EntryChannelFactoryInterface $factory): void;

    /**
     * The customers it shows to. An on-save setting (ADR 0010)
     */
    public function getAudience(): Audience;

    /**
     * Keeps the rows of the groups that stay and creates the rows of new ones through $factory. Change an entry's
     * audience through ChannelsAndAudienceModifierInterface, which passes the entry_audience_group resource's factory
     */
    public function setAudience(Audience $audience, EntryAudienceGroupFactoryInterface $factory): void;

    public function isPublished(): bool;

    /**
     * Marks the entry published at $at. Until drafts arrive, saves write straight to the rows,
     * so publishing only records that the rows are now live
     */
    public function publish(\DateTimeImmutable $at): void;

    /**
     * One block tree for all translations, as it's stored, without upgrading it. Read the rows' tree through
     * StoredDataUpgraderInterface::readBlockTree(), or the working copy's through WorkingCopyProviderInterface
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
