<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Sylius\Resource\Model\TranslatableTrait;
use Sylius\Resource\Model\TranslationInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The root of every content type. Entries use Doctrine single table inheritance keyed by the content type's code (ADR 0001)
 */
abstract class Entry implements EntryInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    protected ?int $id = null;

    protected string $uuid;

    /**
     * The block tree in canonical JSON. Kept as text, not as a database JSON type, because MySQL's JSON type reorders keys
     */
    protected string $blockTree;

    protected \DateTimeImmutable $createdAt;

    protected \DateTimeImmutable $changedAt;

    protected ?\DateTimeImmutable $lastPublishedAt = null;

    public function __construct()
    {
        $this->initializeTranslationsCollection();

        $this->uuid = Uuid::v7()->toRfc4122();
        $this->createdAt = $this->changedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->setBlockTree(new StoredTree(null, []));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function getLastPublishedAt(): ?\DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->lastPublishedAt;
    }

    public function publish(\DateTimeImmutable $at): void
    {
        $this->lastPublishedAt = $this->changedAt = $at;
    }

    public function getBlockTree(): StoredTree
    {
        return (new StoredTreeSerializer())->fromJson($this->blockTree);
    }

    public function setBlockTree(StoredTree $tree): void
    {
        $this->blockTree = (new StoredTreeSerializer())->toJson($tree);
    }

    public function getBlockTreeJson(): string
    {
        return $this->blockTree;
    }

    public function getTitle(): ?string
    {
        return $this->getTranslation()->getTitle();
    }

    public function setTitle(?string $title): void
    {
        $this->getTranslation()->setTitle($title);
    }

    public function getSlug(): ?string
    {
        return $this->getTranslation()->getSlug();
    }

    public function setSlug(?string $slug): void
    {
        $this->getTranslation()->setSlug($slug);
    }

    /**
     * @return EntryTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface
    {
        $translation = $this->doGetTranslation($locale);
        if (!$translation instanceof EntryTranslationInterface) {
            throw new \LogicException(sprintf('The translations of %s must implement %s', static::class, EntryTranslationInterface::class));
        }

        return $translation;
    }
}
