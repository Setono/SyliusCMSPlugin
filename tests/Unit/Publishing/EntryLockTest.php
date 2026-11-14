<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EntryLock;

final class EntryLockTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ManagerRegistry> */
    private ObjectProphecy $managerRegistry;

    private Page $entry;

    /** @var list<string> what happened, in order */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->entry = new Page();

        $manager = $this->prophesize(EntityManagerInterface::class);
        $this->managerRegistry = $this->prophesize(ManagerRegistry::class);
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($manager->reveal());

        $connection = $this->prophesize(Connection::class);
        $manager->getConnection()->willReturn($connection->reveal());
        $connection->beginTransaction()->will($this->record('begin'));
        $connection->commit()->will($this->record('commit'));
        $connection->rollBack()->will($this->record('roll back'));
        $manager->lock($this->entry, LockMode::PESSIMISTIC_READ)->will($this->record('lock to read'));
        $manager->lock($this->entry, LockMode::PESSIMISTIC_WRITE)->will($this->record('lock to write'));
        $manager->refresh($this->entry)->will($this->record('read again'));
        $manager->flush()->will($this->record('flush'));
    }

    /**
     * @test
     */
    public function it_reads_with_the_entrys_row_locked_against_writes_and_read_again(): void
    {
        $result = (new EntryLock($this->managerRegistry->reveal()))->read($this->entry, $this->work('read'));

        self::assertSame('done', $result);
        self::assertSame(['begin', 'lock to read', 'read again', 'read', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_writes_with_the_entrys_row_locked_and_read_again_and_flushes_before_it_commits(): void
    {
        $result = (new EntryLock($this->managerRegistry->reveal()))->write($this->entry, $this->work('write'));

        self::assertSame('done', $result);
        self::assertSame(['begin', 'lock to write', 'read again', 'write', 'flush', 'commit'], $this->calls);
    }

    /**
     * @test
     */
    public function it_rolls_back_and_rethrows_when_the_work_fails(): void
    {
        $failure = new \RuntimeException('Failed');

        try {
            (new EntryLock($this->managerRegistry->reveal()))->write($this->entry, static fn () => throw $failure);
            self::fail('The failure was not rethrown');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }

        self::assertSame(['begin', 'lock to write', 'read again', 'roll back'], $this->calls);
    }

    /**
     * @test
     *
     * @dataProvider provideManagersWithoutTransactions
     */
    public function it_needs_a_doctrine_entity_manager_for_the_entry(?ObjectManager $manager): void
    {
        $this->managerRegistry->getManagerForClass(Page::class)->willReturn($manager);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Page::class);

        (new EntryLock($this->managerRegistry->reveal()))->read($this->entry, $this->work('read'));
    }

    /**
     * @return iterable<string, array{ObjectManager|null}>
     */
    public function provideManagersWithoutTransactions(): iterable
    {
        yield 'no manager' => [null];
        yield 'an object manager' => [$this->prophesize(ObjectManager::class)->reveal()];
    }

    private function work(string $call): \Closure
    {
        $calls = &$this->calls;

        return static function () use ($call, &$calls): string {
            $calls[] = $call;

            return 'done';
        };
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
}
