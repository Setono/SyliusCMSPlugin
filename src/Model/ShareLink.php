<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class ShareLink implements ShareLinkInterface
{
    protected ?int $id = null;

    /**
     * Set as soon as the share link is created
     */
    protected EntryInterface $entry;

    protected string $token = '';

    protected string $channelCode = '';

    protected string $localeCode = '';

    protected string $shownTo = '';

    /**
     * The column holds UTC
     */
    protected ?\DateTimeImmutable $date = null;

    /**
     * Set as soon as the share link is created. The column holds UTC
     */
    protected \DateTimeImmutable $createdAt;

    protected ?string $createdBy = null;

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

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $channelCode): void
    {
        $this->channelCode = $channelCode;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getShownTo(): string
    {
        return $this->shownTo;
    }

    public function setShownTo(string $shownTo): void
    {
        $this->shownTo = $shownTo;
    }

    /**
     * Doctrine hydrates the column in PHP's default time zone, but it holds UTC
     */
    public function getDate(): ?\DateTimeImmutable
    {
        return UtcColumn::read($this->date);
    }

    public function setDate(?\DateTimeImmutable $date): void
    {
        $this->date = $date?->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * Doctrine hydrates the column in PHP's default time zone, but it holds UTC
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return UtcColumn::read($this->createdAt);
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }
}
