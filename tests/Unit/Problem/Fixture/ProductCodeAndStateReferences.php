<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Sylius\Component\Product\Model\ProductInterface;

/**
 * A problem kind about the products entries refer to by their codes, whose problems also depend on whether a product is
 * enabled, so it names two fields, which finds no problems and gives the same entries whatever was written, keeping
 * what it was asked about
 */
final class ProductCodeAndStateReferences implements ReferenceProblemKindInterface
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
        return [ProductInterface::class];
    }

    public static function getReferenceFields(): array
    {
        return [ProductInterface::class => ['code', 'enabled']];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        $this->asked[] = $referenced;

        return $this->entries;
    }

    public function findIn(EntryInterface $entry): iterable
    {
        return [];
    }
}
