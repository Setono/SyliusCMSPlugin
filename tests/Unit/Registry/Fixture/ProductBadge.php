<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * A block type that needs the product of the page it's on
 */
#[BlockType(code: 'product_badge', label: 'Product badge', template: 'product_badge.html.twig', needs: ['product'])]
final class ProductBadge
{
    #[Text(label: 'Text')]
    public ?string $text = null;
}
