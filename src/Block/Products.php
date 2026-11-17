<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Products as ProductsField;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A list of products, each shown through the shop's own product card (@SyliusShop/Product/_box.html.twig), so they
 * match the shop's other lists on every theme. It shows the first items and doesn't paginate. See "Products" in
 * docs/spec/blocks.md
 */
#[BlockType(
    code: 'products',
    label: 'setono_sylius_cms.block_type.products',
    template: '@SetonoSyliusCMSPlugin/block/products.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/products.svg',
)]
final class Products
{
    /** @var ItemList<ProductInterface>|null */
    #[ProductsField(label: 'setono_sylius_cms.field.products.products', limit: 8)]
    public ?ItemList $products = null;

    /** How the products show: the block's root gets the class cms-products--<display>. A carousel shows 4 at once */
    #[Choice(
        label: 'setono_sylius_cms.field.products.display',
        choices: [
            'grid' => 'setono_sylius_cms.choice.products_display.grid',
            'carousel' => 'setono_sylius_cms.choice.products_display.carousel',
        ],
    )]
    public string $display = 'grid';
}
