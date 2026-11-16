<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\AbstractTranslation;

/**
 * The translation of every content type that has no translatable fields of its own.
 * Translations use single table inheritance too, mirroring the entries
 */
class EntryTranslation extends AbstractTranslation implements EntryTranslationInterface
{
    protected ?int $id = null;

    protected ?string $title = null;

    protected ?string $slug = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    /**
     * Off when the translation is held back. A translation made before the entry's first publish starts shown, and one
     * added after it is held back where it's added. It's drafted, so a translation goes live together with its finished text
     */
    protected bool $shown = true;

    /**
     * The translation state of its translatable fields: where each value was translated from
     *
     * @var array<string, array{from: string, fingerprint: string}>
     */
    protected array $translated = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function isShown(): bool
    {
        return $this->shown;
    }

    public function setShown(bool $shown): void
    {
        $this->shown = $shown;
    }

    public function getTranslated(): array
    {
        return $this->translated;
    }

    public function setTranslated(array $translated): void
    {
        $this->translated = $translated;
    }
}
