<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Setono\SyliusCMSPlugin\Factory\EntryAudienceGroupFactoryInterface;
use Setono\SyliusCMSPlugin\Factory\EntryChannelFactoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
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

    /** In UTC. Every save records it, for the admin's "Last edited" */
    protected \DateTimeImmutable $lastEditedAt;

    /** The name of the admin user who saved last, or null when no one it knows did */
    protected ?string $lastEditedBy = null;

    /*
     * The on-save settings, which take effect outside the draft (docs/spec/content-model.md#whats-drafted-and-what-takes-effect-on-save).
     * They're columns and rows beside the entry, so the visible-entries query filters on them in SQL
     */

    protected bool $enabled = true;

    /** In UTC */
    protected ?\DateTimeImmutable $showFrom = null;

    /** In UTC */
    protected ?\DateTimeImmutable $showUntil = null;

    /** @var Collection<array-key, EntryChannel> */
    protected Collection $channels;

    /** @see AudienceMode */
    protected string $audienceMode = 'everyone';

    /** @var Collection<array-key, EntryAudienceGroup> */
    protected Collection $audienceGroups;

    public function __construct()
    {
        $this->initializeTranslationsCollection();
        $this->channels = new ArrayCollection();
        $this->audienceGroups = new ArrayCollection();

        $this->uuid = Uuid::v7()->toRfc4122();
        $this->createdAt = $this->changedAt = $this->lastEditedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->setBlockTree(new StoredTree(null, []));
    }

    /**
     * A clone is a copy of the entry with copies of its translations, which Doctrine doesn't manage,
     * so changing it never changes the rows. A published entry's working copy is one (ADR 0009)
     */
    public function __clone()
    {
        $translations = $this->translations;

        $this->initializeTranslationsCollection();
        $this->translationsCache = [];

        foreach ($translations as $translation) {
            $this->addTranslation(clone $translation);
        }

        // The copy reads the original's rows through collections of its own, so changing the copy's channels or audience
        // leaves the original's alone. A row never changes, and a model can't reach the factories that create new ones
        $this->channels = new ArrayCollection($this->channels->toArray());
        $this->audienceGroups = new ArrayCollection($this->audienceGroups->toArray());
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

    public function setChangedAt(\DateTimeImmutable $changedAt): void
    {
        $this->changedAt = $changedAt;
    }

    public function getLastEditedAt(): \DateTimeImmutable
    {
        return self::readUtc($this->lastEditedAt);
    }

    public function getLastEditedBy(): ?string
    {
        return $this->lastEditedBy;
    }

    public function setLastEdited(\DateTimeImmutable $at, ?string $by): void
    {
        $this->lastEditedAt = $at->setTimezone(new \DateTimeZone('UTC'));
        $this->lastEditedBy = $by;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getDateWindow(): DateWindow
    {
        return new DateWindow(self::readUtc($this->showFrom), self::readUtc($this->showUntil));
    }

    public function setDateWindow(DateWindow $dateWindow): void
    {
        $this->showFrom = $dateWindow->from;
        $this->showUntil = $dateWindow->until;
    }

    public function getChannelCodes(): array
    {
        return self::codes($this->channels);
    }

    public function setChannelCodes(iterable $codes, EntryChannelFactoryInterface $factory): void
    {
        $codes = array_unique([...$codes]);

        foreach ($this->channels as $channel) {
            if (!in_array($channel->getCode(), $codes, true)) {
                $this->channels->removeElement($channel);
            }
        }

        foreach (array_diff($codes, $this->getChannelCodes()) as $code) {
            $this->channels->add($factory->createForEntry($this, $code));
        }
    }

    public function getAudience(): Audience
    {
        return new Audience(AudienceMode::from($this->audienceMode), self::codes($this->audienceGroups));
    }

    public function setAudience(Audience $audience, EntryAudienceGroupFactoryInterface $factory): void
    {
        $this->audienceMode = $audience->mode->value;

        foreach ($this->audienceGroups as $group) {
            if (!in_array($group->getCode(), $audience->groups, true)) {
                $this->audienceGroups->removeElement($group);
            }
        }

        foreach (array_diff($audience->groups, self::codes($this->audienceGroups)) as $code) {
            $this->audienceGroups->add($factory->createForEntry($this, $code));
        }
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

    /**
     * @template T of EntryChannelInterface|EntryAudienceGroupInterface
     *
     * @param Collection<array-key, T> $rows
     *
     * @return list<string> sorted
     */
    private static function codes(Collection $rows): array
    {
        $codes = $rows->map(static fn (EntryChannelInterface|EntryAudienceGroupInterface $row): string => $row->getCode())->toArray();
        sort($codes, \SORT_STRING);

        return $codes;
    }

    /**
     * The date window and last edited columns hold UTC, but Doctrine hydrates them in PHP's default time zone
     *
     * @return ($column is null ? null : \DateTimeImmutable)
     */
    private static function readUtc(?\DateTimeImmutable $column): ?\DateTimeImmutable
    {
        return null === $column ? null : new \DateTimeImmutable($column->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
    }
}
