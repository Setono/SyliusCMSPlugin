<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Revision;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionPruner;

final class RevisionPrunerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RevisionRepositoryInterface> */
    private ObjectProphecy $revisions;

    /** @var list<list<int>> the ids of each removeByIds() call */
    private array $removed = [];

    protected function setUp(): void
    {
        $this->revisions = $this->prophesize(RevisionRepositoryInterface::class);
        $removed = &$this->removed;
        $this->revisions->removeByIds(Argument::type('array'))->will(static function (array $arguments) use (&$removed): void {
            /** @var list<int> $ids */
            $ids = $arguments[0];
            $removed[] = $ids;
        });
    }

    /**
     * @test
     */
    public function it_deletes_the_revisions_beyond_the_newest_ones_to_keep_of_each_entry(): void
    {
        $this->revisions->iterateIdsByEntryNewestFirst()->willReturn([
            ['entry' => 1, 'id' => 9],
            ['entry' => 1, 'id' => 7],
            ['entry' => 1, 'id' => 5],
            ['entry' => 2, 'id' => 8],
            ['entry' => 3, 'id' => 6],
            ['entry' => 3, 'id' => 4],
            ['entry' => 3, 'id' => 3],
            ['entry' => 3, 'id' => 2],
        ]);

        $pruned = (new RevisionPruner($this->revisions->reveal(), 2))->prune();

        self::assertSame(3, $pruned);
        self::assertSame([[5, 3, 2]], $this->removed);
    }

    /**
     * @test
     */
    public function it_deletes_nothing_when_no_entry_has_more_revisions_than_it_keeps(): void
    {
        $this->revisions->iterateIdsByEntryNewestFirst()->willReturn([
            ['entry' => 1, 'id' => 2],
            ['entry' => 2, 'id' => 1],
        ]);
        $this->revisions->removeByIds(Argument::any())->shouldNotBeCalled();

        self::assertSame(0, (new RevisionPruner($this->revisions->reveal(), 1))->prune());
    }

    /**
     * @test
     */
    public function it_deletes_in_batches_of_500(): void
    {
        $this->revisions->iterateIdsByEntryNewestFirst()->willReturn(array_map(
            static fn (int $id): array => ['entry' => 1, 'id' => $id],
            range(1002, 1),
        ));

        $pruned = (new RevisionPruner($this->revisions->reveal(), 1))->prune();

        self::assertSame(1001, $pruned);
        self::assertSame([500, 500, 1], array_map(count(...), $this->removed));
        self::assertSame(1001, $this->removed[0][0]);
        self::assertSame(1, $this->removed[2][0]);
    }

    /**
     * @test
     */
    public function it_tells_how_many_revisions_each_entry_keeps(): void
    {
        self::assertSame(50, (new RevisionPruner($this->revisions->reveal(), 50))->keep);
    }
}
