<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaver;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EntrySettingsSaverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    private EventDispatcher $eventDispatcher;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($this->manager->reveal());

        $calls = &$this->calls;
        $this->manager->wrapInTransaction(Argument::type('callable'))->will(static function (array $arguments) use (&$calls): mixed {
            $transaction = $arguments[0] ?? null;
            self::assertIsCallable($transaction);
            $calls[] = 'begin';
            $result = $transaction();
            $calls[] = 'commit';

            return $result;
        });

        $this->eventDispatcher = new EventDispatcher();
        $this->eventDispatcher->addListener(EntrySettingsChanged::class, function (EntrySettingsChanged $event): void {
            $this->calls[] = sprintf('%s: %s', $event->uuid, implode(', ', $event->changed));
        });
    }

    /**
     * @test
     */
    public function it_applies_the_settings_and_bumps_changed_at_in_a_transaction_then_dispatches_what_changed(): void
    {
        $page = new Page();
        $page->setChannelCodes(['WEB']);
        $calls = &$this->calls;
        $this->manager->wrapInTransaction(Argument::type('callable'))->will(static function (array $arguments) use (&$calls, $page): mixed {
            $transaction = $arguments[0] ?? null;
            self::assertIsCallable($transaction);
            $calls[] = 'begin';
            $transaction();
            $calls[] = sprintf('enabled: %s, changed at %s', var_export($page->isEnabled(), true), $page->getChangedAt()->format('Y-m-d\TH:i:sP'));
            $calls[] = 'commit';

            return null;
        });

        $this->saver()->save($page, new EntrySettings(false, new DateWindow(), ['WEB'], Audience::only('wholesale')));

        self::assertSame([
            'begin',
            'enabled: false, changed at 2026-11-14T11:00:00+00:00',
            'commit',
            $page->getUuid() . ': enabled, audience',
        ], $this->calls);
        self::assertTrue($page->getAudience()->equals(Audience::only('wholesale')));
        self::assertSame('UTC', $page->getChangedAt()->getTimezone()->getName());
    }

    /**
     * @test
     */
    public function it_changes_nothing_when_saving_the_settings_an_entry_already_has(): void
    {
        $page = new Page();
        $page->setChannelCodes(['WEB']);
        $changedAt = $page->getChangedAt();

        $this->saver()->save($page, EntrySettings::of($page));

        self::assertSame([], $this->calls);
        self::assertSame($changedAt, $page->getChangedAt());
    }

    /**
     * @test
     */
    public function it_needs_the_entry_managed_by_the_doctrine_orm(): void
    {
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('No Doctrine entity manager manages %s', Page::class));

        $this->saver()->save(new Page(), new EntrySettings(false, new DateWindow(), [], Audience::everyone()));
    }

    private function saver(): EntrySettingsSaver
    {
        return new EntrySettingsSaver(
            $this->managerRegistry->reveal(),
            new MockClock(new \DateTimeImmutable('2026-11-14 12:00:00', new \DateTimeZone('Europe/Copenhagen'))),
            $this->eventDispatcher,
        );
    }
}
