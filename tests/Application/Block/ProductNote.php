<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * The test application's own block type that needs host-page context: a note about the product of the page it's on,
 * which its template reads from context.product. It's offered only in snippets that declare the product
 */
#[BlockType(code: 'app_product_note', label: 'app.cms.block_type.product_note', template: 'cms/block/product_note.html.twig', needs: ['product'])]
final class ProductNote
{
    #[Text(label: 'app.cms.field.product_note')]
    public ?string $text = null;
}
