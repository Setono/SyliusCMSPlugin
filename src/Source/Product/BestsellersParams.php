<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Sylius\Component\Core\Model\TaxonInterface;

final class BestsellersParams
{
    /**
     * Only the bestsellers in this taxon. Left empty, the channel's bestsellers in every taxon. One that was set but no
     * longer resolves, deleted or disabled, never gets here: the list yields nothing
     */
    #[Taxon(label: 'setono_sylius_cms.field.bestsellers_source.taxon')]
    public ?TaxonInterface $taxon = null;

    /** Whether the bestsellers in the taxons below it are listed too */
    #[Toggle(label: 'setono_sylius_cms.field.bestsellers_source.include_children')]
    public bool $includeChildren = false;
}
