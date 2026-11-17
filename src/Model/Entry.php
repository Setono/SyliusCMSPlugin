<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /** "Hide from search engines", for a content type with its own URL. Shared by all translations, and drafted */
    protected bool $noindex = false;

    /*
     * The on-save settings, which take effect outside the draft (docs/spec/content-model.md#whats-drafted-and-what-takes-effect-on-save).
     * They're columns and rows beside the entry, so the visible-entries query filters on them in SQL
     */

    protected bool $enabled = true;

    /** In UTC */
    protected ?\DateTimeImmutable $showFrom = null;

    /** In UTC */
    protected ?\DateTimeImmutable $showUntil = null;

    /** @var Collection<array-key, EntryChannelInterface> */
    protected Collection $channels;

    /** @see AudienceMode */
    protected string $audienceMode = 'everyone';

    /** @var Collection<array-key, EntryAudienceGroupInterface> */
    protected Collection $audienceGroups;

    /*
     * How many errors and warnings the working copy has, and the fingerprint of the code they were counted with, or
     * null until they're counted. They're written and read in SQL (Problem\ProblemCountUpdater and
     * Problem\ProblemCountProvider), never through the entry, so what an entry holds here can be out of date
     */

    protected int $problemErrors = 0;

    protected int $problemWarnings = 0;

    protected ?string $problemsCountedWith = null;

    /*
     * The locales the working copy is complete in, counted with the problems, or null until they're counted. They're
     * written and read in SQL (Query\CompletenessQuery), never through the entry
     */

    protected ?string $completeLocales = null;

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
        // leaves the original's alone. A row never changes, and a model can't reach the factories that create new ones.
        // So the working copy keeps the original's rows, whose getEntry() is the original, not the copy
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
        return UtcColumn::read($this->lastEditedAt);
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
        return new DateWindow(UtcColumn::read($this->showFrom), UtcColumn::read($this->showUntil));
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

    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function hasChannel(EntryChannelInterface $channel): bool
    {
        return $this->channels->contains($channel);
    }

    public function addChannel(EntryChannelInterface $channel): void
    {
        if ($this !== $channel->getEntry()) {
            throw new \LogicException(sprintf('The channel row %s belongs to another entry', $channel->getCode()));
        }

        if (!$this->hasChannel($channel)) {
            $this->channels->add($channel);
        }
    }

    public function removeChannel(EntryChannelInterface $channel): void
    {
        $this->channels->removeElement($channel);
    }

    public function getAudience(): Audience
    {
        return new Audience(AudienceMode::from($this->audienceMode), self::codes($this->audienceGroups));
    }

    public function setAudienceMode(AudienceMode $mode): void
    {
        $this->audienceMode = $mode->value;
    }

    public function getAudienceGroups(): Collection
    {
        return $this->audienceGroups;
    }

    public function hasAudienceGroup(EntryAudienceGroupInterface $group): bool
    {
        return $this->audienceGroups->contains($group);
    }

    public function addAudienceGroup(EntryAudienceGroupInterface $group): void
    {
        if ($this !== $group->getEntry()) {
            throw new \LogicException(sprintf('The audience group row %s belongs to another entry', $group->getCode()));
        }

        if (!$this->hasAudienceGroup($group)) {
            $this->audienceGroups->add($group);
        }
    }

    public function removeAudienceGroup(EntryAudienceGroupInterface $group): void
    {
        $this->audienceGroups->removeElement($group);
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

    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    public function setNoindex(bool $noindex): void
    {
        $this->noindex = $noindex;
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
}
