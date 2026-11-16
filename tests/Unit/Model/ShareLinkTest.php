<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\ShareLink;

final class ShareLinkTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_holds_an_entrys_token_channel_locale_shown_to_and_date_with_who_made_it_and_when(): void
    {
        $page = new Page();
        $shareLink = new ShareLink();
        self::assertNull($shareLink->getId());
        self::assertSame('', $shareLink->getToken());
        self::assertNull($shareLink->getDate());
        self::assertNull($shareLink->getCreatedBy());

        $shareLink->setEntry($page);
        $shareLink->setToken('s3cr3t');
        $shareLink->setChannelCode('WEB');
        $shareLink->setLocaleCode('da_DK');
        $shareLink->setShownTo('wholesale');
        $shareLink->setDate(new \DateTimeImmutable('2026-12-01 09:00:00', new \DateTimeZone('Europe/Copenhagen')));
        $shareLink->setCreatedAt(new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));
        $shareLink->setCreatedBy('Jane Roe');

        self::assertSame($page, $shareLink->getEntry());
        self::assertSame('s3cr3t', $shareLink->getToken());
        self::assertSame('WEB', $shareLink->getChannelCode());
        self::assertSame('da_DK', $shareLink->getLocaleCode());
        self::assertSame('wholesale', $shareLink->getShownTo());
        self::assertSame('2026-12-01T08:00:00+00:00', $shareLink->getDate()?->format(\DATE_ATOM));
        self::assertSame('2026-11-14T11:30:00+00:00', $shareLink->getCreatedAt()->format(\DATE_ATOM));
        self::assertSame('Jane Roe', $shareLink->getCreatedBy());

        $shareLink->setDate(null);
        self::assertNull($shareLink->getDate(), 'No date shows the page at the time it\'s opened');
    }

    /**
     * @test
     */
    public function it_reads_its_times_in_utc_whatever_time_zone_doctrine_hydrates_them_in(): void
    {
        $shareLink = new ShareLink();
        $hydrated = new \DateTimeImmutable('2026-11-14 11:30:00', new \DateTimeZone('Europe/Copenhagen'));
        (new \ReflectionProperty(ShareLink::class, 'date'))->setValue($shareLink, $hydrated);
        (new \ReflectionProperty(ShareLink::class, 'createdAt'))->setValue($shareLink, $hydrated);

        self::assertSame('2026-11-14T11:30:00+00:00', $shareLink->getDate()?->format(\DATE_ATOM));
        self::assertSame('2026-11-14T11:30:00+00:00', $shareLink->getCreatedAt()->format(\DATE_ATOM));
    }

    /**
     * A project's own content type implements EntryInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_belongs_to_any_entry(): void
    {
        $entry = $this->prophesize(EntryInterface::class)->reveal();
        $shareLink = new ShareLink();

        $shareLink->setEntry($entry);

        self::assertSame($entry, $shareLink->getEntry());
    }
}
