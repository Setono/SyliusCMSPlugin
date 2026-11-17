<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Sylius\Component\Core\Model\TaxonInterface;

final class BestsellersParams
{
    /** Only the bestsellers in this taxon. Without one, the channel's bestsellers in every taxon */
    #[Taxon(label: 'setono_sylius_cms.field.bestsellers_source.taxon')]
    public ?TaxonInterface $taxon = null;

    /** Whether the bestsellers in the taxons below it are listed too */
    #[Toggle(label: 'setono_sylius_cms.field.bestsellers_source.include_children')]
    public bool $includeChildren = false;
}
