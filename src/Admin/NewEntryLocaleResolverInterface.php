<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The locale a new entry's title and slug go into, which the builder then opens in. See "Create dialog" in
 * docs/spec/admin.md
 */
interface NewEntryLocaleResolverInterface
{
    public function resolve(EntryInterface $entry): string;
}
