<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Symfony\Component\Uid\Uuid;

class ScheduledPublish implements ScheduledPublishInterface
{
    protected ?int $id = null;

    /**
     * Every content type extends Entry, the root of the entries' single table inheritance (ADR 0001). Set as soon as
     * the scheduled publish is created
     */
    protected Entry $entry;

    protected string $token;

    /**
     * Set as soon as it's scheduled. The column holds UTC
     */
    protected \DateTimeImmutable $publishAt;

    protected ?string $scheduledBy = null;

    protected ?string $failureReason = null;

    public function __construct()
    {
        $this->token = self::newToken();
    }

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
        if (!$entry instanceof Entry) {
            throw new \InvalidArgumentException(sprintf('A scheduled publish belongs to an entry, which extends %s', Entry::class));
        }

        $this->entry = $entry;
    }

    public function schedule(\DateTimeImmutable $publishAt, ?string $scheduledBy): void
    {
        $this->publishAt = $publishAt->setTimezone(new \DateTimeZone('UTC'));
        $this->scheduledBy = $scheduledBy;
        $this->token = self::newToken();
        $this->failureReason = null;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Doctrine hydrates the column in PHP's default time zone, but it holds UTC
     */
    public function getPublishAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->publishAt->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
    }

    public function getScheduledBy(): ?string
    {
        return $this->scheduledBy;
    }

    public function fail(string $reason): void
    {
        $this->failureReason = $reason;
    }

    public function isFailed(): bool
    {
        return null !== $this->failureReason;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function isOverdue(\DateTimeImmutable $now): bool
    {
        return !$this->isFailed() && $now >= $this->getPublishAt()->modify('+' . self::OVERDUE_AFTER);
    }

    private static function newToken(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
