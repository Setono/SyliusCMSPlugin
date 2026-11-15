<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Model\Page;

final class EntryStatusTest extends TestCase
{
    /**
     * @test
     */
    public function an_entry_is_never_published_until_it_is_published(): void
    {
        $page = new Page();
        self::assertSame('never_published', EntryStatus::of($page, false));

        $page->publish(new \DateTimeImmutable());
        self::assertSame('published', EntryStatus::of($page, false));
    }

    /**
     * @test
     */
    public function it_says_a_published_entry_with_a_draft_has_unpublished_changes(): void
    {
        $page = new Page();
        $page->publish(new \DateTimeImmutable());

        self::assertSame('unpublished_changes', EntryStatus::of($page, true));
    }
}
