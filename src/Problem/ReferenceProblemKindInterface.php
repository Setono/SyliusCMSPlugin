<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * A problem kind about something entries reference beside their own rows, such as the store's customer groups: a
 * broken reference. Its problems change when what's referenced is written, not only when an entry is, so a flush that
 * inserts, updates or deletes one of the referenced classes has the entries that reference it counted again, in the
 * same request. It's optional: a kind that reads only the entry needs nothing more than ProblemKindInterface.
 * See "Problems" in docs/spec/admin.md
 */
interface ReferenceProblemKindInterface extends ProblemKindInterface
{
    /**
     * The classes or interfaces of what it reads, such as Sylius's CustomerGroupInterface. RegisterProblemKindsPass
     * reads them while the container builds, so checking a flush needs no kind
     *
     * @return list<class-string>
     */
    public static function getReferencedClasses(): array;

    /**
     * The entries that may reference what a flush wrote, whose problems of this kind it may have changed. More than
     * those is fine, as they're only counted again. A kind that keeps what it read for the request forgets it here
     *
     * @param non-empty-list<object> $referenced what the flush inserted, updated or deleted, each an instance of one
     *                                           of the referenced classes
     *
     * @return iterable<EntryInterface>
     */
    public function findEntriesReferencing(array $referenced): iterable;
}
