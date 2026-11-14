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
}
