<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A project's layout for pages with a list field
 */
#[Layout(code: 'showcase', contentType: Page::class, label: 'app.showcase', template: 'showcase.html.twig')]
final class Showcase
{
    /** @var ItemList<ProductInterface>|null */
    #[Products(label: 'app.showcase.products')]
    public ?ItemList $products = null;
}
