<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetMerger;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Clash;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Publishing\Autosave;
use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Publishing\EntryLock;
use Setono\SyliusCMSPlugin\Publishing\StaleLockTokenException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\IdentityTranslator;

final class AutosaveTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    /** @var ObjectProphecy<WorkingCopyManagerInterface> */
    private ObjectProphecy $workingCopies;

    /** @var ObjectProphecy<SavedChangeSetRepositoryInterface> */
    private ObjectProphecy $savedChangeSets;

    /** @var ObjectProphecy<ScheduledPublishRepositoryInterface> */
    private ObjectProphecy $scheduledPublishes;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channelRepository;

    /** @var ObjectProphecy<EntryUrlValidatorInterface> */
    private ObjectProphecy $urlValidator;

    private TokenStorage $tokenStorage;

    private Page $entry;

    private Page $workingCopy;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->entry = new Page();
        $this->workingCopy = new Page();
        $this->workingCopy->setBlockTree(new StoredTree(null, [self::heading(self::A, 'Hello'), self::heading(self::B, 'World')]));
        ChannelsAndAudience::modifier()->setChannels($this->workingCopy, ['WEB']);

        $this->manager = $this->prophesize(EntityManagerInterface::class);
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($this->manager->reveal());

        $connection = $this->prophesize(Connection::class);
        $this->manager->getConnection()->willReturn($connection->reveal());
        $connection->beginTransaction()->will($this->record('begin'));
        $connection->commit()->will($this->record('commit'));
        $connection->rollBack()->will($this->record('roll back'));
        $this->manager->lock($this->entry, LockMode::PESSIMISTIC_READ)->will($this->record('lock to read'));
        $this->manager->lock($this->entry, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $this->manager->refresh($this->entry)->will($this->record('read again'));
        $this->manager->flush()->will($this->record('flush'));

        $this->workingCopies = $this->prophesize(WorkingCopyManagerInterface::class);
        $calls = &$this->calls;
        $workingCopy = $this->workingCopy;
        $this->workingCopies->getWorkingCopy($this->entry)->will(static function () use (&$calls, $workingCopy): EntryInterface {
            $calls[] = 'get working copy';

            return $workingCopy;
        });
        $this->workingCopies->save($this->entry, $this->workingCopy)->will($this->record('save working copy'));

        $this->savedChangeSets = $this->prophesize(SavedChangeSetRepositoryInterface::class);
        $this->savedChangeSets->removeSavedBefore(Argument::cetera())->will($this->record('forget old saves'));
        $this->savedChangeSets->record(Argument::cetera())->will($this->record('number the save'));

        // No scheduled publish locks the draft, unless a test schedules one
        $this->scheduledPublishes = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $this->scheduledPublishes->findOneByEntry($this->entry)->willReturn(null);
        $this->urlValidator = $this->prophesize(EntryUrlValidatorInterface::class);
        $this->urlValidator->refuseUrlsTakenByRoutes(Argument::cetera())->willReturn([]);

        // The store's one channel has English and Danish
        $channel = new Channel();
        $channel->setCode('WEB');
        foreach (['en_US', 'da_DK'] as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
        }
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channelRepository */
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channelRepository = $channelRepository;
        $this->channelRepository->findBy([], ['id' => 'ASC'])->willReturn([$channel]);

        // Whoever saves is recorded on the entry. The name is read when the edit is recorded
        $calls = &$this->calls;
        $admin = $this->prophesize(UserInterface::class);
        $admin->getUserIdentifier()->will(static function () use (&$calls): string {
            $calls[] = 'record the edit';

            return 'Jane Roe';
        });
        $this->tokenStorage = new TokenStorage();
        $this->tokenStorage->setToken(new UsernamePasswordToken($admin->reveal(), 'admin', []));
    }

    /**
     * @test
     */
    public function it_loads_the_working_copy_with_its_lock_token_while_no_save_runs(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(7);

        $loaded = $this->autosave()->load($this->entry);

        self::assertSame($this->workingCopy, $loaded->workingCopy);
        self::assertSame(7, $loaded->lockToken);
        self::assertSame(['begin', 'lock to read', 'read again', 'get working copy', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_applies_a_save_made_on_the_latest_lock_token_and_numbers_it(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $changeSet = new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Hello there')]);

        $result = $this->autosave()->save($this->entry, $changeSet, 3);

        self::assertSame(4, $result->saved->lockToken);
        self::assertSame($this->workingCopy, $result->saved->workingCopy);
        self::assertFalse($result->merged);
        self::assertSame([], $result->clashes);
        self::assertSame(['Hello there', 'World'], self::texts($this->workingCopy));
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'forget old saves', 'number the save', 'record the edit', 'save working copy', 'flush', 'commit'], $this->calls);
        $this->savedChangeSets->removeSavedBefore($this->entry, new \DateTimeImmutable('2026-11-13 10:00:00'))->shouldHaveBeenCalledOnce();
        $this->savedChangeSets->record($this->entry, 4, $changeSet, new \DateTimeImmutable('2026-11-14 10:00:00'))->shouldHaveBeenCalledOnce();
        self::assertSame('Jane Roe', $this->entry->getLastEditedBy());
        self::assertEquals(new \DateTimeImmutable('2026-11-14 10:00:00'), $this->entry->getLastEditedAt());
    }

    /**
     * @test
     */
    public function it_merges_a_save_made_on_an_older_lock_token_onto_the_saves_made_since(): void
    {
        $this->workingCopy->setBlockTree(new StoredTree(null, [self::heading(self::A, 'Theirs'), self::heading(self::B, 'World')]));
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(5);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([
            new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Before')]),
            new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Theirs')]),
        ]);
        $mine = new SetValue(self::B, 'text', 'en_US', 'Wide world');

        $result = $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Mine'), $mine]), 3);

        self::assertSame(6, $result->saved->lockToken);
        self::assertTrue($result->merged);
        self::assertSame(
            [['kind' => 'value', 'key' => self::A, 'field' => 'text', 'locale' => 'en_US', 'mine' => 'Mine', 'theirs' => 'Theirs']],
            array_map(static fn (Clash $clash): array => $clash->toArray(), $result->clashes),
        );
        self::assertSame(['Theirs', 'Wide world'], self::texts($this->workingCopy));
        $this->savedChangeSets->record($this->entry, 6, new ChangeSet([$mine]), Argument::any())->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_saves_everything_but_a_slug_another_route_takes_and_says_why(): void
    {
        $this->workingCopy->setCurrentLocale('en_US');
        $this->workingCopy->setSlug('about-us');
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $refused = new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.');
        // The slug a locale ends with is the one checked
        $this->urlValidator->refuseUrlsTakenByRoutes($this->workingCopy, ['en_US' => 'cart', 'da_DK' => 'om-os'])->willReturn(['en_US' => $refused]);
        $text = new SetValue(self::A, 'text', 'en_US', 'Hello there');
        $danish = new SetEntryValue('slug', 'da_DK', 'om-os');
        $title = new SetEntryValue('title', 'en_US', 'Cart');

        $result = $this->autosave()->save($this->entry, new ChangeSet([
            new SetEntryValue('slug', 'en_US', 'car'),
            $text,
            new SetEntryValue('slug', 'en_US', 'cart'),
            $danish,
            $title,
        ]), 3);

        self::assertSame([$refused], $result->refused);
        self::assertSame(4, $result->saved->lockToken);
        self::assertSame(['Hello there', 'World'], self::texts($this->workingCopy));
        self::assertSame('about-us', $this->workingCopy->getTranslation('en_US')->getSlug(), 'The slug keeps its last saved value');
        self::assertSame('Cart', $this->workingCopy->getTranslation('en_US')->getTitle());
        self::assertSame('om-os', $this->workingCopy->getTranslation('da_DK')->getSlug());
        $this->savedChangeSets->record($this->entry, 4, new ChangeSet([$text, $danish, $title]), Argument::any())->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_saves_nothing_when_the_only_change_is_a_slug_another_route_takes(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $refused = new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.');
        $this->urlValidator->refuseUrlsTakenByRoutes($this->workingCopy, ['en_US' => 'cart'])->willReturn(['en_US' => $refused]);

        $result = $this->autosave()->save($this->entry, new ChangeSet([new SetEntryValue('slug', 'en_US', 'cart')]), 3);

        self::assertSame([$refused], $result->refused);
        self::assertSame(3, $result->saved->lockToken);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'flush', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_leaves_a_slug_without_a_locale_for_the_change_set_to_refuse(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $this->urlValidator->refuseUrlsTakenByRoutes(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(InvalidChangeSetException::class);

        $this->autosave()->save($this->entry, new ChangeSet([new SetEntryValue('slug', null, 'cart')]), 3);
    }

    /**
     * @test
     */
    public function it_checks_no_block_field_named_slug(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $this->urlValidator->refuseUrlsTakenByRoutes(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(InvalidChangeSetException::class);

        $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'slug', 'en_US', 'cart')]), 3);
    }

    /**
     * @test
     */
    public function it_checks_no_slug_a_save_empties(): void
    {
        $this->workingCopy->setCurrentLocale('en_US');
        $this->workingCopy->setSlug('about-us');
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $this->urlValidator->refuseUrlsTakenByRoutes(Argument::cetera())->shouldNotBeCalled();

        $result = $this->autosave()->save($this->entry, new ChangeSet([new SetEntryValue('slug', 'en_US', 'cart'), new SetEntryValue('slug', 'en_US', null), new SetEntryValue('title', 'en_US', 'Cart')]), 3);

        self::assertSame([], $result->refused);
        self::assertNull($this->workingCopy->getTranslation('en_US')->getSlug());
    }

    /**
     * @test
     */
    public function it_saves_nothing_when_every_change_clashes(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(4);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Theirs')])]);

        $result = $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Mine')]), 3);

        self::assertSame(4, $result->saved->lockToken);
        self::assertTrue($result->merged);
        self::assertCount(1, $result->clashes);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'flush', 'commit'], $this->calls);
        self::assertNull($this->entry->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_saves_nothing_for_an_empty_save(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);

        $result = $this->autosave()->save($this->entry, new ChangeSet([]), 3);

        self::assertSame(3, $result->saved->lockToken);
        self::assertFalse($result->merged);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'flush', 'commit'], $this->calls);
        self::assertNull($this->entry->getLastEditedBy());
    }

    /**
     * @test
     *
     * @dataProvider provideStaleLockTokens
     */
    public function it_refuses_a_lock_token_it_cannot_merge_onto_and_saves_nothing(int $lockToken, int $latest, int $savesSince): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn($latest);
        $this->savedChangeSets->findChangeSetsSince($this->entry, $lockToken)->willReturn(array_fill(0, $savesSince, new ChangeSet([])));

        try {
            $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Mine')]), $lockToken);
            self::fail('The lock token is refused');
        } catch (StaleLockTokenException) {
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function provideStaleLockTokens(): iterable
    {
        yield 'one the server never gave' => [4, 3, 0];
        yield 'one whose saves since were deleted' => [1, 5, 2];
    }

    /**
     * The admin offers a translation for each locale of the entry's channels. See "Which locales an entry has" in
     * docs/spec/localization.md
     *
     * @test
     */
    public function it_refuses_a_translation_in_a_locale_none_of_the_entrys_channels_has(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);

        $this->autosave()->save($this->entry, new ChangeSet([new SetEntryValue('title', 'da_DK', 'Om os')]), 3);
        self::assertSame('Om os', $this->workingCopy->getTranslation('da_DK')->getTitle());

        $this->expectException(InvalidChangeSetException::class);
        $this->expectExceptionMessage('The entry\'s channels have no locale "sv_SE" to add a translation in');

        $this->autosave()->save($this->entry, new ChangeSet([new SetEntryValue('title', 'sv_SE', 'Om oss')]), 3);
    }

    /**
     * @test
     */
    public function it_saves_nothing_when_the_changes_do_not_apply(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);

        try {
            $this->autosave()->save($this->entry, new ChangeSet([new SetValue('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c', 'text', 'en_US', 'Mine')]), 3);
            self::fail('The change set is refused');
        } catch (InvalidChangeSetException) {
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'roll back'], $this->calls);
    }

    /**
     * @test
     */
    public function it_discards_the_draft_and_numbers_the_discard_as_an_empty_save(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->workingCopies->hasDraft($this->entry)->willReturn(true);
        $this->workingCopies->discard($this->entry)->will($this->record('discard the draft'));

        $loaded = $this->autosave()->discard($this->entry);

        self::assertSame(4, $loaded->lockToken);
        self::assertSame($this->workingCopy, $loaded->workingCopy);
        self::assertSame(['begin', 'lock to write', 'read again', 'forget old saves', 'number the save', 'record the edit', 'discard the draft', 'get working copy', 'flush', 'commit'], $this->calls);
        $this->savedChangeSets->removeSavedBefore($this->entry, new \DateTimeImmutable('2026-11-13 10:00:00'))->shouldHaveBeenCalledOnce();
        $this->savedChangeSets->record($this->entry, 4, new ChangeSet([]), new \DateTimeImmutable('2026-11-14 10:00:00'))->shouldHaveBeenCalledOnce();
        self::assertSame('Jane Roe', $this->entry->getLastEditedBy());
        self::assertEquals(new \DateTimeImmutable('2026-11-14 10:00:00'), $this->entry->getLastEditedAt());
    }

    /**
     * @test
     */
    public function it_leaves_the_lock_token_when_there_is_no_draft_to_discard(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->workingCopies->hasDraft($this->entry)->willReturn(false);
        $this->workingCopies->discard(Argument::any())->shouldNotBeCalled();

        $loaded = $this->autosave()->discard($this->entry);

        self::assertSame(3, $loaded->lockToken);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'flush', 'commit'], $this->calls);
        self::assertNull($this->entry->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_replaces_the_working_copy_with_content_in_the_entry_format_and_numbers_it_as_an_empty_save(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $restored = clone $this->workingCopy;
        $restored->setCurrentLocale('en_US');
        $restored->setTitle('About us');
        $restored->setBlockTree(new StoredTree(null, [self::heading(self::B, 'As it was')]));

        $loaded = $this->autosave()->replace($this->entry, self::entryFormat($restored));

        self::assertSame(4, $loaded->lockToken);
        self::assertSame($this->workingCopy, $loaded->workingCopy);
        self::assertSame(['As it was'], self::texts($this->workingCopy));
        self::assertSame('About us', $this->workingCopy->getTranslation('en_US')->getTitle());
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'forget old saves', 'number the save', 'record the edit', 'save working copy', 'flush', 'commit'], $this->calls);
        $this->savedChangeSets->removeSavedBefore($this->entry, new \DateTimeImmutable('2026-11-13 10:00:00'))->shouldHaveBeenCalledOnce();
        $this->savedChangeSets->record($this->entry, 4, new ChangeSet([]), new \DateTimeImmutable('2026-11-14 10:00:00'))->shouldHaveBeenCalledOnce();
        self::assertSame('Jane Roe', $this->entry->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_replaces_nothing_with_content_that_is_not_the_entry(): void
    {
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->record(Argument::cetera())->shouldNotBeCalled();

        try {
            $this->autosave()->replace($this->entry, self::entryFormat(new Page()));
            self::fail('Content of another entry replaced the working copy');
        } catch (InvalidEntryFormatException) {
        }

        self::assertSame(['Hello', 'World'], self::texts($this->workingCopy));
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'roll back'], $this->calls);
        self::assertNull($this->entry->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_refuses_a_save_while_a_scheduled_publish_locks_the_draft(): void
    {
        $scheduledPublish = $this->scheduledPublish();
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);

        try {
            $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Mine')]), 3);
            self::fail('The save was not refused');
        } catch (DraftLockedException $e) {
            self::assertSame($scheduledPublish, $e->scheduledPublish);
            self::assertSame('A publish scheduled for 2026-11-20T08:00:00+00:00 locks the draft until it runs or is cancelled', $e->getMessage());
        }

        self::assertSame(['Hello', 'World'], self::texts($this->workingCopy));
        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
        self::assertNull($this->entry->getLastEditedBy());
    }

    /**
     * @test
     */
    public function it_refuses_a_discard_while_a_scheduled_publish_locks_the_draft(): void
    {
        $this->scheduledPublish();
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->workingCopies->hasDraft($this->entry)->willReturn(true);
        $this->workingCopies->discard(Argument::any())->shouldNotBeCalled();

        try {
            $this->autosave()->discard($this->entry);
            self::fail('The discard was not refused');
        } catch (DraftLockedException) {
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
    }

    /**
     * @test
     */
    public function it_refuses_to_replace_the_working_copy_while_a_scheduled_publish_locks_the_draft(): void
    {
        $this->scheduledPublish();
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $restored = clone $this->workingCopy;
        $restored->setBlockTree(new StoredTree(null, [self::heading(self::B, 'As it was')]));

        try {
            $this->autosave()->replace($this->entry, self::entryFormat($restored));
            self::fail('The working copy was replaced');
        } catch (DraftLockedException) {
        }

        self::assertSame(['Hello', 'World'], self::texts($this->workingCopy));
        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
    }

    /**
     * @test
     */
    public function it_leaves_the_draft_unlocked_after_a_failed_scheduled_publish(): void
    {
        $this->scheduledPublish()->fail('Not on a Sunday');
        $this->savedChangeSets->findLatestLockToken($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);

        $result = $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Hello there')]), 3);

        self::assertSame(4, $result->saved->lockToken);
        self::assertSame(['Hello there', 'World'], self::texts($this->workingCopy));
    }

    /**
     * A publish of the entry scheduled for 2026-11-20 08:00 UTC, which waits
     */
    private function scheduledPublish(): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->setEntry($this->entry);
        $scheduledPublish->schedule(new \DateTimeImmutable('2026-11-20 08:00:00', new \DateTimeZone('UTC')), 'Jane Roe');
        $this->scheduledPublishes->findOneByEntry($this->entry)->willReturn($scheduledPublish);

        return $scheduledPublish;
    }

    private function autosave(): Autosave
    {
        $clock = new MockClock('2026-11-14 10:00:00');

        return new Autosave(
            new EntryLock($this->managerRegistry->reveal()),
            $this->workingCopies->reveal(),
            $this->savedChangeSets->reveal(),
            new ChangeSetMerger(),
            new ChangeSetApplier(
                new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()),
                new FieldHandlerLocator(new ServiceLocator([
                    TextHandler::class => static fn (): TextHandler => new TextHandler(),
                    ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
                ])),
                self::contentTypes(),
                Conditions::registry(),
                new LayoutRegistry([], new FieldReader()),
            ),
            $clock,
            new EditRecorder($this->tokenStorage, $clock),
            new EntryDenormalizer(self::contentTypes()),
            $this->urlValidator->reveal(),
            $this->scheduledPublishes->reveal(),
            new ChannelLocales($this->channelRepository->reveal()),
        );
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
    }

    /**
     * The entry in the entry format, as CanonicalJson::decode() gives it
     *
     * @return array<array-key, mixed>
     */
    private static function entryFormat(EntryInterface $entry): array
    {
        $data = CanonicalJson::decode(CanonicalJson::encode((new EntryNormalizer(self::contentTypes()))->normalize($entry)));
        self::assertIsArray($data);

        return $data;
    }

    /**
     * Prophecy binds a promise's closure to the double, so it reaches the calls by reference
     */
    private function record(string $call): \Closure
    {
        $calls = &$this->calls;

        return static function () use ($call, &$calls): void {
            $calls[] = $call;
        };
    }

    private static function heading(string $key, string $text): StoredBlock
    {
        $block = new StoredBlock($key, 'heading', 1);
        $block->setValue('text', 'en_US', $text);

        return $block;
    }

    /**
     * @return list<mixed>
     */
    private static function texts(EntryInterface $entry): array
    {
        return array_map(static function (StoredBlock $block): mixed {
            $text = $block->fields['text'] ?? null;

            return is_array($text) ? $text['en_US'] ?? null : null;
        }, $entry->getBlockTree()->blocks);
    }
}
