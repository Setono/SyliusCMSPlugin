<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * A problem kind about something entries reference beside their own rows, such as the store's customer groups: a
 * broken reference. Its problems change when what's referenced is written, not only when an entry is, so a flush that
 * inserts, updates or deletes one of the referenced classes has the entries that reference it counted again, in the
 * same request, or only a flush that deletes one or changes the field entries refer to it by, for a class it names
 * one for. It's optional: a kind that reads only the entry needs nothing more than ProblemKindInterface.
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
     * The field that entries refer to the records of a referenced class by, by class, such as a product's code, or the
     * fields, when its problems depend on more than one, such as a snippet's audience and whether it's enabled. An
     * entry can only start or stop referring to such a record when it's deleted or one of those fields changes, so only
     * a flush that does either counts the entries again: not inserting one, nor changing anything else, so writing
     * products runs no query. A referenced class it leaves out counts on every insert, update and delete, as a customer
     * group does, since an entry may name a group's code before the group is added again. RegisterProblemKindsPass
     * reads them while the container builds
     *
     * @return array<class-string, string|non-empty-list<string>>
     */
    public static function getReferenceFields(): array;

    /**
     * The entries that may reference what a flush wrote, whose problems of this kind it may have changed. More than
     * those is fine, as they're only counted again. A kind that keeps what it read for the request forgets it here
     *
     * @param non-empty-list<object> $referenced what the flush inserted, updated or deleted, each an instance of one
     *                                           of the referenced classes
     * @param list<string> $formerValues what the fields getReferenceFields() names held before the flush changed
     *                                   them, such as a product's former code, which entries may still refer to
     *
     * @return iterable<EntryInterface>
     */
    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable;
}
