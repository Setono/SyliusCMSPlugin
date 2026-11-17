<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding\Product;

use Setono\SyliusCMSPlugin\Field\Choice;

/**
 * The parameters of the Product attribute binding: which of the store's attributes it shows
 */
final class AttributeParams
{
    /** The attribute's code */
    #[Choice(label: 'setono_sylius_cms.field.product_attribute_binding.attribute', required: true, choicesProvider: ProductAttributeChoicesProvider::class)]
    public ?string $attribute = null;
}
