<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * A snippet declares the host-page context it needs in the builder's entry settings, which decides the block types its
 * picker offers and the slots it can be placed in, and a slot on a product page gives it the product. See "Snippets
 * and their context" in docs/spec/slots.md and "Block picker" in docs/spec/builder.md
 */
final class HostPageContextTest extends UITestCase
{
    use Catalog;

    /**
     * @test
     */
    public function it_lets_an_admin_user_set_a_snippet_s_context_to_product_pages_add_a_block_that_needs_the_product_place_it_under_the_price_and_see_it_on_a_product_page(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');
        $snippet = self::createSnippet('Size guide');
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $snippet->getUuid()));
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('Size guide');

        // On any page, the picker names the block type that needs a product page with why, and doesn't offer it
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $picker = $browser->getByRole('dialog', ['name' => 'Add block']);
        Expect::locator($picker->getByRole('list', ['name' => 'Not available here']))->toContainText('Product noteNeeds: Product page');
        Expect::locator($picker->getByRole('button', ['name' => 'Product note']))->toHaveCount(0);
        $browser->keyboard()->press('Escape');

        // Shown on product pages, the picker offers it
        $browser->getByLabel('Shown on')->selectOption('Product page');
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Product note'])->click();
        $browser->getByRole('textbox', ['name' => 'Note'])->fill('Size guide for');

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // Its overview page offers the slots on product pages, and places it under the price
        $browser->getByRole('link', ['name' => 'Back'])->click();
        $placements = $browser->getByRole('region', ['name' => 'Placements']);
        $placements->getByLabel('Slot')->selectOption('Product page: under the price');
        $placements->getByRole('button', ['name' => 'Add placement'])->click();
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The placement has been added.');

        // The product page gives it its product
        $shop = $this->visit('/en_US/products/t-shirt');
        Expect::locator($shop->locator('.cms-slot.cms-slot--product-after-price .app-product-note'))->toHaveText('Size guide for T-shirt');
    }
}
