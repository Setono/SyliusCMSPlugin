<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;

/**
 * A problem kind about the customer groups entries reference, which finds no problems and gives the same entries
 * whatever was written, keeping what it was asked about
 */
final class CustomerGroupReferences implements ReferenceProblemKindInterface
{
    /** @var list<list<object>> what each findEntriesReferencing() was given */
    public array $asked = [];

    /**
     * @param list<EntryInterface> $entries
     */
    public function __construct(private readonly array $entries = [])
    {
    }

    public static function getReferencedClasses(): array
    {
        return [CustomerGroupInterface::class];
    }

    public static function getReferenceFields(): array
    {
        return [];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        $this->asked[] = $referenced;

        return (fn (): \Generator => yield from $this->entries)();
    }

    public function findIn(EntryInterface $entry): iterable
    {
        return [];
    }
}
