<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * A Products block filled by Same taxon in a snippet for product pages: the editor finds the source in "Filled by" only
 * once the snippet is shown on product pages, and shoppers see the product's neighbours in its main taxon there. See
 * "Sources" in docs/spec/builder.md and "Product sources" in docs/spec/sources.md
 */
final class SameTaxonTest extends UITestCase
{
    use Catalog;

    /**
     * @test
     */
    public function an_admin_user_fills_a_products_block_with_same_taxon_in_a_snippet_for_product_pages_and_shoppers_see_the_product_s_neighbours(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        $basic = self::createProduct('BASIC', 'Basic');
        self::placeInTaxon(self::createProduct('STRIPED', 'Striped'), $shirts);
        self::placeInTaxon($basic, $shirts);
        self::placeInTaxon(self::createProduct('PLAIN', 'Plain'), $shirts);
        self::placeInTaxon(self::createProduct('VNECK', 'V-neck'), $shirts, 0);
        self::placeInTaxon(self::createProduct('MUG', 'Mug'), self::createTaxon('MUGS', 'Mugs'));
        self::setMainTaxon($basic, $shirts);
        $snippet = self::createSnippet('More T-shirts');
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $snippet->getUuid()));
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Products', 'exact' => true])->click();

        // Shown on any page, there's no product, so Same taxon isn't offered
        $fields = $browser->getByRole('complementary');
        $filledBy = $fields->getByRole('combobox', ['name' => 'Filled by']);
        Expect::locator($filledBy)->toHaveValue('');
        Expect::locator($filledBy->getByRole('option', ['name' => 'Newest', 'exact' => true]))->toHaveCount(1);
        Expect::locator($filledBy->getByRole('option', ['name' => 'Same taxon', 'exact' => true]))->toHaveCount(0);
        Expect::locator($filledBy->getByRole('option', ['name' => 'Associations', 'exact' => true]))->toHaveCount(0);

        // Shown on product pages, it is
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        $tree->getByRole('button', ['name' => 'Entry settings'])->click();
        $browser->getByLabel('Shown on')->selectOption('Product page');
        $tree->getByRole('button', ['name' => 'Products', 'exact' => true])->click();
        Expect::locator($filledBy->getByRole('option', ['name' => 'Same taxon', 'exact' => true]))->toHaveCount(1);
        Expect::locator($filledBy->getByRole('option', ['name' => 'Associations', 'exact' => true]))->toHaveCount(1);
        $filledBy->selectOption('same_taxon_products');
        $fields->getByRole('spinbutton', ['name' => 'Items to show'])->fill('2');

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $browser->getByRole('link', ['name' => 'Back'])->click();
        $placements = $browser->getByRole('region', ['name' => 'Placements']);
        $placements->getByLabel('Slot')->selectOption('Product page: under the price');
        $placements->getByRole('button', ['name' => 'Add placement'])->click();
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The placement has been added.');

        // The product page lists the first two other products in its main taxon, by their position there
        $shop = $this->visit('/en_US/products/basic');
        self::assertTexts(['V-neck', 'Striped'], $shop->locator('.cms-slot--product-after-price .cms-products .sylius-product-name'));
    }
}
