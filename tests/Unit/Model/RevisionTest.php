<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Revision;

final class RevisionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_holds_an_entrys_published_content_with_who_published_it_when_and_whether_it_was_scheduled(): void
    {
        $page = new Page();
        $revision = new Revision();
        self::assertNull($revision->getId());
        self::assertSame('', $revision->getContent());
        self::assertNull($revision->getPublishedBy());
        self::assertFalse($revision->isScheduled());

        $revision->setEntry($page);
        $revision->setContent('{"uuid":"0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e"}');
        $revision->setPublishedAt(new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));
        $revision->setPublishedBy('Jane Roe');
        $revision->setScheduled(true);

        self::assertSame($page, $revision->getEntry());
        self::assertSame('{"uuid":"0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e"}', $revision->getContent());
        self::assertSame('2026-11-14T11:30:00+00:00', $revision->getPublishedAt()->format(\DATE_ATOM));
        self::assertSame('Jane Roe', $revision->getPublishedBy());
        self::assertTrue($revision->isScheduled());
    }

    /**
     * @test
     */
    public function it_reads_when_it_was_published_in_utc_whatever_time_zone_doctrine_hydrates_it_in(): void
    {
        $revision = new Revision();
        $publishedAt = new \ReflectionProperty(Revision::class, 'publishedAt');
        $publishedAt->setValue($revision, new \DateTimeImmutable('2026-11-14 11:30:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame('2026-11-14T11:30:00+00:00', $revision->getPublishedAt()->format(\DATE_ATOM));
    }

    /**
     * A project's own content type implements EntryInterface, as a Sylius model implements its interface
     *
     * @test
     */
    public function it_belongs_to_any_entry(): void
    {
        $entry = $this->prophesize(EntryInterface::class)->reveal();
        $revision = new Revision();

        $revision->setEntry($entry);

        self::assertSame($entry, $revision->getEntry());
    }
}
