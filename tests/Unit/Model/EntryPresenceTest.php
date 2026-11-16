<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\Page;

final class EntryPresenceTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_holds_who_has_an_entry_open_as_of_when_they_were_last_seen(): void
    {
        $page = new Page();
        $presence = new EntryPresence($page, 'jane', 'Jane', new \DateTimeImmutable('2026-11-14 10:00:00'));

        self::assertNull($presence->getId());
        self::assertSame($page, $presence->getEntry());
        self::assertSame('jane', $presence->getEditor());

        $seenAt = new \DateTimeImmutable('2026-11-14 10:00:05');
        $presence->see('Jane Roe', $seenAt);

        self::assertSame('Jane Roe', $presence->getName());
        self::assertSame($seenAt, $presence->getSeenAt());
    }

    /**
     * A project's own content type implements EntryInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_belongs_to_any_entry(): void
    {
        $entry = $this->prophesize(EntryInterface::class)->reveal();

        $presence = new EntryPresence($entry, 'jane', 'Jane', new \DateTimeImmutable('2026-11-14 10:00:00'));

        self::assertSame($entry, $presence->getEntry());
    }
}
