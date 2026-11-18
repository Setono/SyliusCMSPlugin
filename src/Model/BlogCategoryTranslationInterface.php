<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

interface BlogCategoryTranslationInterface extends EntryTranslationInterface
{
    /**
     * What the category is about, which its layout shows under its title and its meta description falls back to
     */
    public function getDescription(): ?string;

    public function setDescription(?string $description): void;
}
