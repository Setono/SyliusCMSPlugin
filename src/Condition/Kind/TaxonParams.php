<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Taxons;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\TaxonInterface;

final class TaxonParams
{
    /** @var ItemList<TaxonInterface>|null the picked taxons the shopper may see, stored as taxon codes */
    #[Taxons(label: 'setono_sylius_cms.field.taxon_condition.taxons', sources: false, required: true)]
    public ?ItemList $taxons = null;
}
