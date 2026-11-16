<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A slug an entry had in a locale before a publish changed it. Its URL redirects to the entry's current one, with the
 * content type's current prefix, while no live entry claims it. See "Old slugs" in docs/spec/urls-and-seo.md
 */
interface OldSlugInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function setEntry(EntryInterface $entry): void;

    public function getLocaleCode(): string;

    public function setLocaleCode(string $localeCode): void;

    public function getSlug(): string;

    public function setSlug(string $slug): void;
}
