<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class OldSlug implements OldSlugInterface
{
    protected ?int $id = null;

    /**
     * Every content type extends Entry, the root of the entries' single table inheritance (ADR 0001). Set as soon as
     * the old slug is created
     */
    protected Entry $entry;

    protected string $localeCode = '';

    protected string $slug = '';

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
            throw new \InvalidArgumentException(sprintf('An old slug belongs to an entry, which extends %s', Entry::class));
        }

        $this->entry = $entry;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }
}
