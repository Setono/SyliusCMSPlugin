<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisher;

final class EntryPublisherTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_marks_the_entry_published_now_in_utc_and_flushes(): void
    {
        $page = new Page();

        $manager = $this->prophesize(ObjectManager::class);
        $manager->flush()->shouldBeCalledOnce();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Page::class)->willReturn($manager->reveal());

        $clock = $this->prophesize(ClockInterface::class);
        $clock->now()->willReturn(new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        (new EntryPublisher($managerRegistry->reveal(), $clock->reveal()))->publish($page);

        self::assertTrue($page->isPublished());
        self::assertSame('2026-11-14T11:30:00+00:00', $page->getLastPublishedAt()?->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_needs_a_doctrine_manager_for_the_entry(): void
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Page::class)->willReturn(null);

        $clock = $this->prophesize(ClockInterface::class);
        $clock->now()->willReturn(new \DateTimeImmutable());

        $this->expectException(\LogicException::class);

        (new EntryPublisher($managerRegistry->reveal(), $clock->reveal()))->publish(new Page());
    }
}
