<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

/**
 * A problem kind about the products and taxons entries refer to by their codes, as a broken reference is, which finds
 * no problems and gives the same entries whatever was written, keeping what it was asked about
 */
final class ProductAndTaxonCodeReferences implements ReferenceProblemKindInterface
{
    /** @var list<list<object>> what each findEntriesReferencing() was given */
    public array $asked = [];

    /** @var list<list<string>> the former values each findEntriesReferencing() was given */
    public array $formerValues = [];

    /**
     * @param list<EntryInterface> $entries
     */
    public function __construct(private readonly array $entries = [])
    {
    }

    public static function getReferencedClasses(): array
    {
        return [ProductInterface::class, TaxonInterface::class];
    }

    public static function getReferenceFields(): array
    {
        return [ProductInterface::class => 'code', TaxonInterface::class => 'code'];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        $this->asked[] = $referenced;
        $this->formerValues[] = $formerValues;

        return $this->entries;
    }

    public function findIn(EntryInterface $entry): iterable
    {
        return [];
    }
}
