<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

/**
 * A problem kind about the channels and taxons entries reference, which finds no problems and gives the same entries
 * whatever was written, keeping what it was asked about
 */
final class ChannelAndTaxonReferences implements ReferenceProblemKindInterface
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
        return [ChannelInterface::class, TaxonInterface::class];
    }

    public function findEntriesReferencing(array $referenced): iterable
    {
        $this->asked[] = $referenced;

        return $this->entries;
    }

    public function findIn(EntryInterface $entry): iterable
    {
        return [];
    }
}
