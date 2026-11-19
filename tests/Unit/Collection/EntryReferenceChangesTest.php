<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\EntryReferenceChanges;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\EntryReference;

/**
 * How an entry's rows of the reference table change when it's published: the rows of the UUIDs its indexed fields no
 * longer hold go, and the UUIDs they newly hold get rows. See "Categories" in docs/spec/collections.md
 */
final class EntryReferenceChangesTest extends TestCase
{
    private const NEWS = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const RECIPES = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7c';

    private const EVENTS = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7d';

    /**
     * @test
     */
    public function it_adds_a_row_for_each_uuid_of_a_first_publish(): void
    {
        $changes = EntryReferenceChanges::of(['categories' => [self::NEWS, self::RECIPES]], []);

        self::assertSame([['categories', self::NEWS], ['categories', self::RECIPES]], $changes->added);
        self::assertSame([], $changes->removed);
        self::assertFalse($changes->isEmpty());
    }

    /**
     * @test
     */
    public function it_removes_the_rows_of_the_uuids_a_field_no_longer_holds_and_keeps_the_others(): void
    {
        $news = self::row('categories', self::NEWS);
        $recipes = self::row('categories', self::RECIPES);

        $changes = EntryReferenceChanges::of(['categories' => [self::RECIPES, self::EVENTS]], [$news, $recipes]);

        self::assertSame([['categories', self::EVENTS]], $changes->added);
        self::assertSame([$news], $changes->removed);
    }

    /**
     * @test
     */
    public function it_changes_nothing_when_the_fields_hold_what_the_rows_do_in_another_order(): void
    {
        $changes = EntryReferenceChanges::of(
            ['categories' => [self::RECIPES, self::NEWS]],
            [self::row('categories', self::NEWS), self::row('categories', self::RECIPES)],
        );

        self::assertTrue($changes->isEmpty());
    }

    /**
     * The same UUID in two indexed fields is a row of each
     *
     * @test
     */
    public function it_tells_the_fields_apart(): void
    {
        $changes = EntryReferenceChanges::of(
            ['categories' => [self::NEWS], 'series' => [self::NEWS]],
            [self::row('categories', self::NEWS), self::row('related', self::NEWS)],
        );

        self::assertSame([['series', self::NEWS]], $changes->added);
        self::assertCount(1, $changes->removed);
        self::assertSame('related', $changes->removed[0]->getField());
    }

    /**
     * @test
     */
    public function it_removes_every_row_when_the_fields_hold_nothing(): void
    {
        $rows = [self::row('categories', self::NEWS), self::row('categories', self::RECIPES)];

        $changes = EntryReferenceChanges::of(['categories' => []], $rows);

        self::assertSame([], $changes->added);
        self::assertSame($rows, $changes->removed);
    }

    /**
     * A row is written once, so a duplicate row, which no write leaves, goes too
     *
     * @test
     */
    public function it_removes_a_row_that_repeats_another(): void
    {
        $first = self::row('categories', self::NEWS);
        $second = self::row('categories', self::NEWS);

        $changes = EntryReferenceChanges::of(['categories' => [self::NEWS]], [$first, $second]);

        self::assertSame([], $changes->added);
        self::assertSame([$second], $changes->removed);
    }

    private static function row(string $field, string $uuid): EntryReference
    {
        $row = new EntryReference();
        $row->setEntry(new BlogPost());
        $row->setField($field);
        $row->setUuid($uuid);

        return $row;
    }
}
