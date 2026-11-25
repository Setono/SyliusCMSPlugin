<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Symfony\Component\Uid\Uuid;

class Import implements ImportInterface
{
    protected ?int $id = null;

    protected string $uuid;

    protected string $filename = '';

    protected ?string $path = null;

    /**
     * Set as soon as it's uploaded. The column holds UTC
     */
    protected \DateTimeImmutable $createdAt;

    protected ImportState $state = ImportState::Checking;

    protected ?\DateTimeImmutable $startedAt = null;

    /** @var array<string, mixed>|null */
    protected ?array $dryRun = null;

    protected bool $overwrite = false;

    protected bool $publish = false;

    /** @var array<string, mixed>|null */
    protected ?array $result = null;

    protected ?\DateTimeImmutable $finishedAt = null;

    protected ?string $failure = null;

    public function __construct()
    {
        $this->uuid = Uuid::v7()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
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
        $this->createdAt = self::utc($createdAt);
    }

    public function getState(): ImportState
    {
        return $this->state;
    }

    public function isWaiting(): bool
    {
        return ImportState::Checking === $this->state || ImportState::Importing === $this->state;
    }

    public function isAbandonable(): bool
    {
        return ImportState::Checking === $this->state || ImportState::Checked === $this->state;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return UtcColumn::read($this->startedAt);
    }

    public function start(\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = self::utc($startedAt);
    }

    public function isRunning(): bool
    {
        return $this->isWaiting() && null !== $this->startedAt;
    }

    public function retry(): void
    {
        $this->startedAt = null;
    }

    public function check(array $dryRun): void
    {
        $this->assertState(ImportState::Checking);

        $this->state = ImportState::Checked;
        $this->dryRun = $dryRun;
        $this->startedAt = null;
    }

    public function getDryRun(): ?array
    {
        return $this->dryRun;
    }

    public function confirm(bool $overwrite, bool $publish): void
    {
        $this->assertState(ImportState::Checked);

        $this->state = ImportState::Importing;
        $this->overwrite = $overwrite;
        $this->publish = $publish;
    }

    public function isOverwrite(): bool
    {
        return $this->overwrite;
    }

    public function isPublish(): bool
    {
        return $this->publish;
    }

    public function finish(array $result, \DateTimeImmutable $finishedAt): void
    {
        $this->assertState(ImportState::Checking, ImportState::Importing);

        $this->state = ImportState::Finished;
        $this->result = $result;
        $this->path = null;
        $this->finishedAt = self::utc($finishedAt);
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function abandon(\DateTimeImmutable $abandonedAt): void
    {
        if (!$this->isAbandonable()) {
            throw new \LogicException(sprintf('The import %s is %s', $this->uuid, $this->state->value));
        }

        $this->state = ImportState::Abandoned;
        $this->path = null;
        $this->finishedAt = self::utc($abandonedAt);
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return UtcColumn::read($this->finishedAt);
    }

    public function fail(string $reason, \DateTimeImmutable $failedAt): void
    {
        $this->assertState(ImportState::Checking, ImportState::Importing);

        $this->state = ImportState::Failed;
        $this->failure = $reason;
        $this->path = null;
        $this->finishedAt = self::utc($failedAt);
    }

    public function stop(\DateTimeImmutable $stoppedAt): void
    {
        if (!$this->isRunning()) {
            throw new \LogicException(sprintf('The import %s isn\'t running', $this->uuid));
        }

        $this->state = ImportState::Failed;
        $this->failure = null;
        $this->path = null;
        $this->finishedAt = self::utc($stoppedAt);
    }

    public function isStopped(): bool
    {
        return ImportState::Failed === $this->state && null === $this->failure;
    }

    public function getFailure(): ?string
    {
        return $this->failure;
    }

    private function assertState(ImportState ...$states): void
    {
        if (!in_array($this->state, $states, true)) {
            throw new \LogicException(sprintf('The import %s is %s', $this->uuid, $this->state->value));
        }
    }

    private static function utc(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'));
    }
}
