<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EntryPresence;
use Setono\SyliusCMSPlugin\Model\Page;

final class EntryPresenceTest extends TestCase
{
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
}
