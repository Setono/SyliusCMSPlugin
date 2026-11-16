<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;

final class SavedChangeSetTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_holds_one_save_of_an_entry_s_working_copy_by_its_number(): void
    {
        $page = new Page();
        $savedAt = new \DateTimeImmutable('2026-11-14 10:00:00');

        $save = new SavedChangeSet($page, 4, '{"changes":[]}', $savedAt);

        self::assertNull($save->getId());
        self::assertSame($page, $save->getEntry());
        self::assertSame(4, $save->getLockToken());
        self::assertSame('{"changes":[]}', $save->getChanges());
        self::assertSame($savedAt, $save->getSavedAt());
    }

    /**
     * A project's own content type implements EntryInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_belongs_to_any_entry(): void
    {
        $entry = $this->prophesize(EntryInterface::class)->reveal();

        $save = new SavedChangeSet($entry, 4, '{"changes":[]}', new \DateTimeImmutable('2026-11-14 10:00:00'));

        self::assertSame($entry, $save->getEntry());
    }
}
