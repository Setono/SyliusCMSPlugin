<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Setono\SyliusCMSPlugin\Field\Choice;

final class ProductAssociationsParams
{
    /**
     * The code of the association type whose products are listed, or null for every type. A type the store no longer
     * has narrows to nothing
     */
    #[Choice(
        label: 'setono_sylius_cms.field.product_associations_source.association_type',
        help: 'setono_sylius_cms.field.product_associations_source.association_type_help',
        choicesProvider: ProductAssociationTypeChoicesProvider::class,
    )]
    public ?string $associationType = null;
}
