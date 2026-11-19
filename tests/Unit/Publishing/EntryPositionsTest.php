<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Publishing\EntryPositions;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;

final class EntryPositionsTest extends TestCase
{
    /**
     * @test
     */
    public function it_numbers_the_entries_in_their_new_order_from_0_and_gives_only_the_positions_that_change(): void
    {
        $positions = EntryPositions::reorder(['a' => 0, 'b' => 1, 'c' => 2], ['c', 'a', 'b']);

        self::assertSame(['c' => 0, 'a' => 1, 'b' => 2], $positions);
    }

    /**
     * Editors order one page of the admin list at a time, so the entries on it take the places they had between them,
     * and the others keep theirs
     *
     * @test
     */
    public function it_puts_the_entries_ordered_in_the_places_they_had_between_them_among_the_others(): void
    {
        $positions = EntryPositions::reorder(['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3, 'e' => 4], ['d', 'b']);

        self::assertSame(['d' => 1, 'b' => 3], $positions);
    }

    /**
     * Entries that have no position yet, such as those of a content type that was ordered otherwise before, go last in
     * the order they were given, and positions that are the same, or that leave gaps, are numbered again
     *
     * @test
     */
    public function it_numbers_entries_without_a_position_or_with_the_same_one_after_those_with_one(): void
    {
        $positions = EntryPositions::reorder(['a' => null, 'b' => 5, 'c' => 5, 'd' => null], ['c', 'b']);

        self::assertSame(['c' => 0, 'b' => 1, 'a' => 2, 'd' => 3], $positions);
    }

    /**
     * @test
     */
    public function it_changes_nothing_for_the_order_the_entries_have(): void
    {
        self::assertSame([], EntryPositions::reorder(['a' => 0, 'b' => 1, 'c' => 2], ['a', 'b', 'c']));
    }

    /**
     * @test
     */
    public function it_refuses_an_entry_that_is_not_among_them(): void
    {
        $this->expectException(InvalidEntrySettingsException::class);
        $this->expectExceptionMessage('The entry x is not one of the entries to order');

        EntryPositions::reorder(['a' => 0, 'b' => 1], ['b', 'x']);
    }
}
