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
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
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

    /** @var ObjectProphecy<HomepageRepositoryInterface> */
    private ObjectProphecy $homepageRepository;

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

        $this->homepageRepository = $this->prophesize(HomepageRepositoryInterface::class);
        $this->homepageRepository->findByChannelCodes(Argument::any())->willReturn([]);

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
     * A channel has one homepage, which the homepage rows' unique channel code holds to, and Doctrine inserts rows before
     * it deletes others. So the page that was a chosen channel's homepage loses it in a flush of its own, before the
     * transaction adds the page's rows
     *
     * @test
     */
    public function it_takes_the_chosen_channels_off_the_page_that_was_their_homepage_in_a_flush_of_its_own(): void
    {
        $page = new Page();
        $about = new Page();
        $aboutWeb = Homepages::factory()->createForPage($about, 'WEB');
        $about->addHomepage($aboutWeb);
        $about->addHomepage(Homepages::factory()->createForPage($about, 'MOBILE'));
        $this->homepageRepository->findByChannelCodes(['B2B', 'WEB'])->willReturn([$aboutWeb]);
        $calls = &$this->calls;
        $this->manager->flush()->will(static function () use (&$calls, $page, $about): void {
            $calls[] = sprintf('flush: the page is the homepage of %s, the other page of %s', implode(', ', $page->getHomepageChannelCodes()) ?: 'none', implode(', ', $about->getHomepageChannelCodes()));
        });

        $this->saver()->save($page, new EntrySettings(true, new DateWindow(), [], Audience::everyone(), ['WEB', 'B2B']));

        self::assertSame([
            'begin',
            'flush: the page is the homepage of none, the other page of MOBILE',
            'commit',
            $page->getUuid() . ': homepage',
            $about->getUuid() . ': homepage',
        ], $this->calls);
        self::assertSame(['B2B', 'WEB'], $page->getHomepageChannelCodes());
        self::assertEquals(new \DateTimeImmutable('2026-11-14T11:00:00Z'), $about->getChangedAt(), 'Its "Homepage of" changed');
        self::assertNull($about->getLastEditedBy(), 'Nobody edited it');
    }

    /**
     * @test
     */
    public function it_flushes_nothing_more_when_no_other_page_was_the_homepage_of_the_chosen_channels(): void
    {
        $page = new Page();

        $this->saver()->save($page, new EntrySettings(true, new DateWindow(), [], Audience::everyone(), ['WEB']));

        self::assertSame(['begin', 'commit', $page->getUuid() . ': homepage'], $this->calls);
        $this->manager->flush()->shouldNotHaveBeenCalled();
        self::assertSame(['WEB'], $page->getHomepageChannelCodes());
    }

    /**
     * @test
     */
    public function it_looks_for_no_other_homepage_when_the_homepages_stay(): void
    {
        $page = new Page();

        $this->saver()->save($page, new EntrySettings(false, new DateWindow(), [], Audience::everyone()));

        $this->homepageRepository->findByChannelCodes(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * Only a page can be a channel's homepage. See "Homepage" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_refuses_homepages_for_an_entry_other_than_a_page(): void
    {
        $announcement = new Announcement();
        $this->managerRegistry->getManagerForClass(Announcement::class)->willReturn($this->manager->reveal());

        try {
            $this->saver()->save($announcement, new EntrySettings(false, new DateWindow(), [], Audience::everyone(), ['WEB']));
            self::fail('The homepage was not refused');
        } catch (InvalidEntrySettingsException $e) {
            self::assertSame('Only a page can be a channel\'s homepage', $e->getMessage());
        }

        self::assertSame([], $this->calls);
        self::assertTrue($announcement->isEnabled(), 'Nothing is saved');
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
            Homepages::modifier($this->homepageRepository->reveal()),
        );
    }
}
