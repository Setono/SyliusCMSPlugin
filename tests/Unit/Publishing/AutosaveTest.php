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
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\Autosave;
use Setono\SyliusCMSPlugin\Publishing\StaleLockTokenException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
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

    private Page $entry;

    private Page $workingCopy;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->entry = new Page();
        $this->workingCopy = new Page();
        $this->workingCopy->setBlockTree(new StoredTree(null, [self::heading(self::A, 'Hello'), self::heading(self::B, 'World')]));

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
        $this->savedChangeSets->add(Argument::cetera())->will($this->record('number the save'));
    }

    /**
     * @test
     */
    public function it_loads_the_working_copy_with_its_lock_token_while_no_save_runs(): void
    {
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(7);

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
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);
        $changeSet = new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Hello there')]);

        $result = $this->autosave()->save($this->entry, $changeSet, 3);

        self::assertSame(4, $result->saved->lockToken);
        self::assertSame($this->workingCopy, $result->saved->workingCopy);
        self::assertFalse($result->merged);
        self::assertSame([], $result->clashes);
        self::assertSame(['Hello there', 'World'], self::texts($this->workingCopy));
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'forget old saves', 'number the save', 'save working copy', 'commit'], $this->calls);
        $this->savedChangeSets->removeSavedBefore($this->entry, new \DateTimeImmutable('2026-11-13 10:00:00'))->shouldHaveBeenCalledOnce();
        $this->savedChangeSets->add($this->entry, 4, $changeSet, new \DateTimeImmutable('2026-11-14 10:00:00'))->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_merges_a_save_made_on_an_older_lock_token_onto_the_saves_made_since(): void
    {
        $this->workingCopy->setBlockTree(new StoredTree(null, [self::heading(self::A, 'Theirs'), self::heading(self::B, 'World')]));
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(5);
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
        $this->savedChangeSets->add($this->entry, 6, new ChangeSet([$mine]), Argument::any())->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_saves_nothing_when_every_change_clashes(): void
    {
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(4);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Theirs')])]);

        $result = $this->autosave()->save($this->entry, new ChangeSet([new SetValue(self::A, 'text', 'en_US', 'Mine')]), 3);

        self::assertSame(4, $result->saved->lockToken);
        self::assertTrue($result->merged);
        self::assertCount(1, $result->clashes);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_saves_nothing_for_an_empty_save(): void
    {
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(3);
        $this->savedChangeSets->findChangeSetsSince($this->entry, 3)->willReturn([]);

        $result = $this->autosave()->save($this->entry, new ChangeSet([]), 3);

        self::assertSame(3, $result->saved->lockToken);
        self::assertFalse($result->merged);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'commit'], $this->calls);
    }

    /**
     * @test
     *
     * @dataProvider provideStaleLockTokens
     */
    public function it_refuses_a_lock_token_it_cannot_merge_onto_and_saves_nothing(int $lockToken, int $version, int $savesSince): void
    {
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn($version);
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
     * @test
     */
    public function it_saves_nothing_when_the_changes_do_not_apply(): void
    {
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(3);
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
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(3);
        $this->workingCopies->hasDraft($this->entry)->willReturn(true);
        $this->workingCopies->discard($this->entry)->will($this->record('discard the draft'));

        $loaded = $this->autosave()->discard($this->entry);

        self::assertSame(4, $loaded->lockToken);
        self::assertSame($this->workingCopy, $loaded->workingCopy);
        self::assertSame(['begin', 'lock to write', 'read again', 'forget old saves', 'number the save', 'discard the draft', 'get working copy', 'commit'], $this->calls);
        $this->savedChangeSets->removeSavedBefore($this->entry, new \DateTimeImmutable('2026-11-13 10:00:00'))->shouldHaveBeenCalledOnce();
        $this->savedChangeSets->add($this->entry, 4, new ChangeSet([]), new \DateTimeImmutable('2026-11-14 10:00:00'))->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_leaves_the_lock_token_when_there_is_no_draft_to_discard(): void
    {
        $this->savedChangeSets->findLatestVersion($this->entry)->willReturn(3);
        $this->workingCopies->hasDraft($this->entry)->willReturn(false);
        $this->workingCopies->discard(Argument::any())->shouldNotBeCalled();

        $loaded = $this->autosave()->discard($this->entry);

        self::assertSame(3, $loaded->lockToken);
        self::assertSame(['begin', 'lock to write', 'read again', 'get working copy', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_needs_an_entity_manager_for_the_entry(): void
    {
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn(null);

        $this->expectException(\LogicException::class);

        $this->autosave()->load($this->entry);
    }

    private function autosave(): Autosave
    {
        return new Autosave(
            $this->managerRegistry->reveal(),
            $this->workingCopies->reveal(),
            $this->savedChangeSets->reveal(),
            new ChangeSetMerger(),
            new ChangeSetApplier(
                new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()),
                new FieldHandlerLocator(new ServiceLocator([
                    TextHandler::class => static fn (): TextHandler => new TextHandler(),
                    ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
                ])),
            ),
            new MockClock('2026-11-14 10:00:00'),
        );
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
