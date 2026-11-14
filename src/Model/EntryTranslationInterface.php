<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * An entry's content in one locale
 */
interface EntryTranslationInterface extends ResourceInterface, TranslationInterface
{
    public function getId(): ?int;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    /**
     * Set only for content types with their own URL
     */
    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;
}
