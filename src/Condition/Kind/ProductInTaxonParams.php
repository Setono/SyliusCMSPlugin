<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Taxons;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductInTaxonParams
{
    /** @var ItemList<TaxonInterface>|null the picked taxons the shopper may see, stored as taxon codes */
    #[Taxons(label: 'setono_sylius_cms.field.product_in_taxon_condition.taxons', sources: false, required: true)]
    public ?ItemList $taxons = null;

    /** Whether a product in a taxon below one of them matches too */
    #[Toggle(label: 'setono_sylius_cms.field.product_in_taxon_condition.include_children')]
    public bool $includeChildren = false;
}
