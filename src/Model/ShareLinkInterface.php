<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Preview\SampleObject;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A revocable link that shows one entry's draft in the shop, in one channel and locale, to anyone who has it, with no
 * admin account. Its random token alone decides what's shown, and Shown to and Date are fixed when it's created, and for
 * a snippet the placement whose host page shows it and the sample object.
 * Revoking it deletes it. See "Share links" in docs/spec/publishing.md
 */
interface ShareLinkInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function setEntry(EntryInterface $entry): void;

    /**
     * The random token in the link's _cms_share query parameter
     */
    public function getToken(): string;

    public function setToken(string $token): void;

    public function getChannelCode(): string;

    public function setChannelCode(string $channelCode): void;

    public function getLocaleCode(): string;

    public function setLocaleCode(string $localeCode): void;

    /**
     * Shown to: the customer group value the page is shown to, a customer group code, "@guest" or "@no_group"
     */
    public function getShownTo(): string;

    public function setShownTo(string $shownTo): void;

    /**
     * Date: the time the page is shown at, in UTC, or null for the time it's opened
     */
    public function getDate(): ?\DateTimeImmutable;

    public function setDate(?\DateTimeImmutable $date): void;

    /**
     * In UTC
     */
    public function getCreatedAt(): \DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): void;

    /**
     * Who made it, as the admin names editors, or null when no one is known
     */
    public function getCreatedBy(): ?string;

    public function setCreatedBy(?string $createdBy): void;

    /**
     * For a snippet, the placement whose host page shows it, or null for a plain page, such as once the placement is
     * removed
     */
    public function getPlacement(): ?PlacementInterface;

    public function setPlacement(?PlacementInterface $placement): void;

    /**
     * For a snippet, the sample object its page shows it with, or null for none
     */
    public function getSample(): ?SampleObject;

    public function setSample(?SampleObject $sample): void;
}
