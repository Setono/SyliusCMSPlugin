<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Where Shown to and Date start in the builder's preview of an entry, and what a share link of it fixes them at.
 * See "Shown to and Date" in docs/spec/targeting.md
 */
interface StartingValuesInterface
{
    /**
     * The customer group value the page is shown to: "@guest" when the entry's audience allows guests, and otherwise
     * the first value it allows of those Shown to offers, which are "@no_group" and then the store's customer groups
     *
     * @return string a customer group code, "@guest" or "@no_group"
     */
    public function shownTo(EntryInterface $entry): string;

    /**
     * The start of the entry's date window when that's in the future, and otherwise null, which is now
     */
    public function date(EntryInterface $entry): ?\DateTimeImmutable;
}
