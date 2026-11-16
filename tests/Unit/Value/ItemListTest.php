<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Value;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Value\ItemList;

final class ItemListTest extends TestCase
{
    /**
     * @test
     */
    public function it_iterates_and_counts_the_items_it_was_given(): void
    {
        $first = new \stdClass();
        $second = new \stdClass();
        $list = ItemList::of([$first, $second]);

        self::assertSame([$first, $second], [...$list]);
        self::assertCount(2, $list);
        self::assertSame([$first, $second], $list->toArray());
        self::assertFalse($list->isEmpty());
    }

    /**
     * @test
     */
    public function it_is_empty_without_items(): void
    {
        $list = ItemList::empty();

        self::assertSame([], [...$list]);
        self::assertCount(0, $list);
        self::assertTrue($list->isEmpty());
    }

    /**
     * @test
     */
    public function it_reads_a_lazy_adapter_only_when_it_is_first_read(): void
    {
        $resolved = new \ArrayObject();
        $list = ItemList::lazy(static function () use ($resolved): AdapterInterface {
            $resolved->append(true);

            return new ArrayAdapter(['a', 'b', 'c']);
        }, 2);

        self::assertCount(0, $resolved);

        self::assertSame(['a', 'b'], [...$list]);
        self::assertCount(2, $list);
        self::assertCount(1, $resolved);
    }

    /**
     * @test
     */
    public function it_shows_only_the_first_slice_of_a_lazy_adapter_without_counting_it(): void
    {
        $adapter = new class() implements AdapterInterface {
            public function getNbResults(): int
            {
                throw new \LogicException('A list that does not paginate never counts');
            }

            public function getSlice(int $offset, int $length): iterable
            {
                return array_slice(['a', 'b', 'c', 'd'], $offset, $length);
            }
        };

        $list = ItemList::lazy(static fn (): AdapterInterface => $adapter, 3);

        self::assertSame(['a', 'b', 'c'], [...$list]);
        self::assertCount(3, $list);
    }

    /**
     * @test
     */
    public function it_returns_its_adapter(): void
    {
        $adapter = new ArrayAdapter(['a']);

        self::assertSame($adapter, ItemList::lazy(static fn (): AdapterInterface => $adapter, 8)->adapter());
        self::assertSame(['a'], [...ItemList::of(['a'])->adapter()->getSlice(0, 1)]);
    }

    /**
     * @test
     */
    public function it_pages_through_a_lazy_adapter_by_its_limit(): void
    {
        $list = ItemList::lazy(static fn (): AdapterInterface => new ArrayAdapter(['a', 'b', 'c', 'd', 'e']), 2);

        $pager = $list->pager(2);

        self::assertSame(['c', 'd'], [...$pager->getCurrentPageResults()]);
        self::assertSame(3, $pager->getNbPages());
    }

    /**
     * @test
     */
    public function it_pages_through_the_items_it_was_given_on_one_page(): void
    {
        $pager = ItemList::of(['a', 'b', 'c'])->pager(1);

        self::assertSame(['a', 'b', 'c'], [...$pager->getCurrentPageResults()]);
        self::assertSame(1, $pager->getNbPages());
    }
}
