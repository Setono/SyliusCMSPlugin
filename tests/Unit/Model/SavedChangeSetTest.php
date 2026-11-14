<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\SavedChangeSet;

final class SavedChangeSetTest extends TestCase
{
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
        self::assertSame(4, $save->getVersion());
        self::assertSame('{"changes":[]}', $save->getChanges());
        self::assertSame($savedAt, $save->getSavedAt());
    }
}
