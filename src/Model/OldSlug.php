<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class OldSlug implements OldSlugInterface
{
    protected ?int $id = null;

    /**
     * Set as soon as the old slug is created
     */
    protected EntryInterface $entry;

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
