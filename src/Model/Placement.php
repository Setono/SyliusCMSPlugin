<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Symfony\Component\Uid\Uuid;

class Placement implements PlacementInterface
{
    protected ?int $id = null;

    protected string $uuid;

    /** @var list<array<string, mixed>> */
    protected array $pageScope = [];

    public function __construct(
        protected SnippetInterface $snippet,
        protected string $slot,
        protected int $position,
    ) {
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

    public function getSnippet(): SnippetInterface
    {
        return $this->snippet;
    }

    public function getSlot(): string
    {
        return $this->slot;
    }

    public function getPageScope(): array
    {
        return $this->pageScope;
    }

    public function setPageScope(array $pageScope): void
    {
        $this->pageScope = $pageScope;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
