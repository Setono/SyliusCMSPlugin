<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Taxons, hand-picked by code in the order editors put them, or filled by a taxon source. Hydrated as
 * ItemList<TaxonInterface> of the taxons the shopper may see. See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'taxons')]
final class Taxons extends ListField
{
    public function itemType(): string
    {
        return 'taxon';
    }

    public function itemClass(): string
    {
        return TaxonInterface::class;
    }
}
