<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Value;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

/**
 * What a template gets for a list field, such as a Media list or a Products field: iterable and countable over the items
 * that show, resolved lazily on first read. A hand-picked list shows the items it holds that resolve, in their order,
 * with broken references left out. A list a source fills shows the first slice of the source's adapter, as many items as
 * its limit, and never counts. See "Shipped field types" in docs/spec/extending.md and "Paging and rendering" in
 * docs/spec/sources.md
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final class ItemList implements \IteratorAggregate, \Countable
{
    /** @var AdapterInterface<T>|null */
    private ?AdapterInterface $adapter = null;

    /** @var list<T>|null the items that show, once read */
    private ?array $items = null;

    /** @var array<int, Pagerfanta<T>> the pages asked for, by page */
    private array $pagers = [];

    /**
     * @param \Closure(): AdapterInterface<T> $resolve
     * @param int<1, max>|null $limit how many items show, or null for all of them
     */
    private function __construct(
        private readonly \Closure $resolve,
        private readonly ?int $limit,
    ) {
    }

    /**
     * A list of the items, which all show, such as the media items a hand-picked list holds that resolve
     *
     * @template I
     *
     * @param list<I> $items
     *
     * @return self<I>
     */
    public static function of(array $items): self
    {
        return new self(static fn (): AdapterInterface => new ArrayAdapter($items), null);
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        return self::of([]);
    }

    /**
     * A list whose adapter is made when it's first read, such as a source's, which shows its first $limit items
     *
     * @template I
     *
     * @param \Closure(): AdapterInterface<I> $adapter
     * @param int<1, max> $limit
     *
     * @return self<I>
     */
    public static function lazy(\Closure $adapter, int $limit): self
    {
        return new self($adapter, $limit);
    }

    /**
     * The Pagerfanta adapter its items come from
     *
     * @return AdapterInterface<T>
     */
    public function adapter(): AdapterInterface
    {
        return $this->adapter ??= ($this->resolve)();
    }

    /**
     * A page of its adapter's items, $limit to a page, or every item on one page for a list without a limit, such as
     * for the paginated Entries block. The same page is the same Pagerfanta, so the pass before rendering counts the list
     * and its template shows the page without counting it again
     *
     * @param int<1, max> $page
     *
     * @return Pagerfanta<T>
     */
    public function pager(int $page): Pagerfanta
    {
        if (isset($this->pagers[$page])) {
            return $this->pagers[$page];
        }

        $pager = new Pagerfanta($this->adapter());
        $pager->setMaxPerPage($this->limit ?? max(1, $pager->getNbResults()));
        $pager->setCurrentPage($page);

        return $this->pagers[$page] = $pager;
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * How many items show
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    public function isEmpty(): bool
    {
        return [] === $this->toArray();
    }

    /**
     * The items that show
     *
     * @return list<T>
     */
    public function toArray(): array
    {
        if (null === $this->items) {
            $adapter = $this->adapter();
            $this->items = array_values([...$adapter->getSlice(0, $this->limit ?? $adapter->getNbResults())]);
        }

        return $this->items;
    }
}
