<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

interface BlogPostTranslationInterface extends EntryTranslationInterface
{
    /**
     * A short text about the post, which its meta description falls back to and its cards show
     */
    public function getExcerpt(): ?string;

    public function setExcerpt(?string $excerpt): void;
}
