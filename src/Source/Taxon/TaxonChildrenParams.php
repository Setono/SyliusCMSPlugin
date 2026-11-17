<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Taxon;

use Setono\SyliusCMSPlugin\Field\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;

final class TaxonChildrenParams
{
    /** The taxon whose children are listed. Without one, or when the shopper may not see it, the list is empty */
    #[Taxon(label: 'setono_sylius_cms.field.taxon_children_source.taxon', required: true)]
    public ?TaxonInterface $taxon = null;
}
