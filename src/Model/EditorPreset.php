<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Symfony\Component\Uid\Uuid;

class EditorPreset implements EditorPresetInterface
{
    protected ?int $id = null;

    protected string $uuid;

    protected string $content = '';

    protected \DateTimeImmutable $createdAt;

    protected \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->uuid = Uuid::v7()->toRfc4122();
        $this->createdAt = $this->changedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = strtolower($uuid);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return UtcColumn::read($this->createdAt);
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return UtcColumn::read($this->changedAt);
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): void
    {
        $this->changedAt = $changedAt->setTimezone(new \DateTimeZone('UTC'));
    }
}
