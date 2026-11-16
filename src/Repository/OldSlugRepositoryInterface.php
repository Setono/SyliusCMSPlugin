<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;

/**
 * The slugs entries had before a publish changed them. See "Old slugs" in docs/spec/urls-and-seo.md
 */
interface OldSlugRepositoryInterface
{
    /**
     * The entry's old slugs, by locale code, and newest first within a locale
     *
     * @return list<OldSlugInterface>
     */
    public function findByEntry(EntryInterface $entry): array;
}
