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
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaver;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class EntrySettingsSaverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    private EventDispatcher $eventDispatcher;

    /** @var ObjectProphecy<EntryUrlValidatorInterface> */
    private ObjectProphecy $urlValidator;

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

        $this->urlValidator = $this->prophesize(EntryUrlValidatorInterface::class);
        $this->urlValidator->validateChannels(Argument::cetera())->willReturn([]);

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
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);
        $calls = &$this->calls;
        $this->manager->wrapInTransaction(Argument::type('callable'))->will(static function (array $arguments) use (&$calls, $page): mixed {
            $transaction = $arguments[0] ?? null;
            self::assertIsCallable($transaction);
            $calls[] = 'begin';
            $transaction();
            $calls[] = sprintf('enabled: %s, changed at %s', var_export($page->isEnabled(), true), $page->getChangedAt()->format('Y-m-d\TH:i:sP'));
            $calls[] = sprintf('edited by %s at %s', (string) $page->getLastEditedBy(), $page->getLastEditedAt()->format('Y-m-d\TH:i:sP'));
            $calls[] = 'commit';

            return null;
        });

        $this->saver()->save($page, new EntrySettings(false, new DateWindow(), ['WEB'], Audience::only('wholesale')));

        self::assertSame([
            'begin',
            'enabled: false, changed at 2026-11-14T11:00:00+00:00',
            'edited by jane at 2026-11-14T11:00:00+00:00',
            'commit',
            $page->getUuid() . ': enabled, audience',
        ], $this->calls);
        self::assertTrue($page->getAudience()->equals(Audience::only('wholesale')));
        self::assertSame('UTC', $page->getChangedAt()->getTimezone()->getName());
    }

    /**
     * @test
     */
    public function it_checks_the_entrys_urls_in_the_channels_it_would_be_in_when_they_change(): void
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);

        $this->saver()->save($page, new EntrySettings(true, new DateWindow(), ['MOBILE', 'WEB'], Audience::everyone()));

        $this->urlValidator->validateChannels($page, ['MOBILE', 'WEB'])->shouldHaveBeenCalledOnce();
        self::assertSame(['MOBILE', 'WEB'], $page->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_refuses_channels_where_another_entry_has_published_one_of_the_entrys_published_urls(): void
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);
        $this->urlValidator->validateChannels($page, ['MOBILE', 'WEB'])->willReturn([
            new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/about-us is already the URL of "About" in Mobile.'),
            new ValidationError(null, 'slug', 'da_DK', 'The URL /da_DK/om-os is already the URL of "Om" in Mobile.'),
        ]);

        try {
            $this->saver()->save($page, new EntrySettings(false, new DateWindow(), ['MOBILE', 'WEB'], Audience::everyone()));
            self::fail('The channels were not refused');
        } catch (InvalidEntrySettingsException $e) {
            self::assertSame('The URL /en_US/about-us is already the URL of "About" in Mobile. The URL /da_DK/om-os is already the URL of "Om" in Mobile.', $e->getMessage());
        }

        self::assertSame([], $this->calls);
        self::assertSame(['WEB'], $page->getChannelCodes());
        self::assertTrue($page->isEnabled(), 'Nothing is saved');
    }

    /**
     * @test
     */
    public function it_checks_no_url_when_the_channels_stay(): void
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);

        $this->saver()->save($page, new EntrySettings(false, new DateWindow(), ['WEB'], Audience::everyone()));

        $this->urlValidator->validateChannels(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function it_changes_nothing_when_saving_the_settings_an_entry_already_has(): void
    {
        $page = new Page();
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);
        $changedAt = $page->getChangedAt();

        $this->saver()->save($page, EntrySettings::of($page));

        self::assertSame([], $this->calls);
        self::assertSame($changedAt, $page->getChangedAt());
        self::assertNull($page->getLastEditedBy());
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
        $clock = new MockClock(new \DateTimeImmutable('2026-11-14 12:00:00', new \DateTimeZone('Europe/Copenhagen')));
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('jane', null), 'admin', []));

        return new EntrySettingsSaver(
            $this->managerRegistry->reveal(),
            $clock,
            $this->eventDispatcher,
            ChannelsAndAudience::modifier(),
            new EditRecorder($tokenStorage, $clock),
            $this->urlValidator->reveal(),
        );
    }
}
