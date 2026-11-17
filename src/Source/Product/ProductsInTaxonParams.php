<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductsInTaxonParams
{
    public const SORT_POSITION = 'position';

    public const SORT_NEWEST = 'newest';

    public const SORT_PRICE = 'price';

    public const SORT_NAME = 'name';

    /** The taxon the products are in. Without one, or when the shopper may not see it, the list is empty */
    #[Taxon(label: 'setono_sylius_cms.field.products_in_taxon_source.taxon', required: true)]
    public ?TaxonInterface $taxon = null;

    /** Whether the products in the taxons below it are listed too */
    #[Toggle(label: 'setono_sylius_cms.field.products_in_taxon_source.include_children')]
    public bool $includeChildren = false;

    #[Choice(label: 'setono_sylius_cms.field.products_in_taxon_source.sort', choices: [
        self::SORT_POSITION => 'setono_sylius_cms.choice.products_in_taxon_sort.position',
        self::SORT_NEWEST => 'setono_sylius_cms.choice.products_in_taxon_sort.newest',
        self::SORT_PRICE => 'setono_sylius_cms.choice.products_in_taxon_sort.price',
        self::SORT_NAME => 'setono_sylius_cms.choice.products_in_taxon_sort.name',
    ])]
    public string $sort = self::SORT_POSITION;
}
