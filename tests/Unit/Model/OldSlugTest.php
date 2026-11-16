<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\OldSlug;
use Setono\SyliusCMSPlugin\Model\Page;

final class OldSlugTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_holds_a_slug_an_entry_had_in_a_locale(): void
    {
        $page = new Page();
        $oldSlug = new OldSlug();
        self::assertNull($oldSlug->getId());
        self::assertSame('', $oldSlug->getLocaleCode());
        self::assertSame('', $oldSlug->getSlug());

        $oldSlug->setEntry($page);
        $oldSlug->setLocaleCode('en_US');
        $oldSlug->setSlug('about');

        self::assertSame($page, $oldSlug->getEntry());
        self::assertSame('en_US', $oldSlug->getLocaleCode());
        self::assertSame('about', $oldSlug->getSlug());
    }

    /**
     * @test
     */
    public function it_belongs_to_an_entry_of_a_content_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An old slug belongs to an entry, which extends Setono\SyliusCMSPlugin\Model\Entry');

        (new OldSlug())->setEntry($this->prophesize(EntryInterface::class)->reveal());
    }
}
