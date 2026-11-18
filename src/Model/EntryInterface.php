<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
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
     * Its channel rows. Change them through ChannelsAndAudienceModifierInterface, which creates rows through the
     * entry_channel resource's factory
     *
     * @return Collection<array-key, EntryChannelInterface>
     */
    public function getChannels(): Collection;

    public function hasChannel(EntryChannelInterface $channel): bool;

    /**
     * @throws \LogicException when the row belongs to another entry
     */
    public function addChannel(EntryChannelInterface $channel): void;

    public function removeChannel(EntryChannelInterface $channel): void;

    /**
     * The customers it shows to. An on-save setting (ADR 0010)
     */
    public function getAudience(): Audience;

    /**
     * Change the audience through ChannelsAndAudienceModifierInterface, which changes the mode and the groups together
     */
    public function setAudienceMode(AudienceMode $mode): void;

    /**
     * The rows of the groups its audience mode applies to. Change them through ChannelsAndAudienceModifierInterface,
     * which creates rows through the entry_audience_group resource's factory
     *
     * @return Collection<array-key, EntryAudienceGroupInterface>
     */
    public function getAudienceGroups(): Collection;

    public function hasAudienceGroup(EntryAudienceGroupInterface $group): bool;

    /**
     * @throws \LogicException when the row belongs to another entry
     */
    public function addAudienceGroup(EntryAudienceGroupInterface $group): void;

    public function removeAudienceGroup(EntryAudienceGroupInterface $group): void;

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
     * "Hide from search engines", for a content type with its own URL: the head tags then ask crawlers not to index its
     * pages. Shared by all translations, and drafted. See "SEO fields" in docs/spec/urls-and-seo.md
     */
    public function isNoindex(): bool;

    public function setNoindex(bool $noindex): void;

    /**
     * Where it sits among the entries of its content type, for a content type ordered by position, which editors drag
     * entries into in the admin list. An on-save setting. Null for an entry of a content type ordered otherwise
     */
    public function getPosition(): ?int;

    public function setPosition(?int $position): void;

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
