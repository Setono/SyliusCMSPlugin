<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * A Products block filled by a source: the editor picks "In taxon" in "Filled by", fills in its parameters inline and
 * the number to show, sees how many products the source yields next to "Filled by", and shoppers see that many in the
 * source's order. See "Sources" in docs/spec/builder.md and "List fields" in docs/spec/sources.md
 */
final class ProductsInTaxonTest extends UITestCase
{
    use Catalog;

    /**
     * @test
     */
    public function it_lets_an_admin_user_fill_a_products_block_with_the_t_shirts_by_name_and_shoppers_see_the_first_four(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        foreach (['STRIPED' => 'Striped', 'VNECK' => 'V-neck', 'BASIC' => 'Basic', 'POLO' => 'Polo', 'ANCHOR' => 'Anchor', 'LONG' => 'Long sleeve'] as $code => $name) {
            self::placeInTaxon(self::createProduct($code, $name), $shirts);
        }
        self::createProduct('MUG', 'Mug');
        $this->signInAsAdmin();
        $entry = self::createPage('T-shirts', 't-shirts');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Products', 'exact' => true])->click();

        // Hand-picked comes first, then the sources of products
        $fields = $page->getByRole('complementary');
        $filledBy = $fields->getByRole('combobox', ['name' => 'Filled by']);
        Expect::locator($filledBy)->toHaveValue('');
        $filledBy->selectOption('products_in_taxon');

        // Its parameters are filled in inline, and the number to show starts at the field's limit
        $fields->getByRole('button', ['name' => 'Taxon', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Search taxons'])->fill('shirts');
        $page->getByRole('option', ['name' => 'T-shirts', 'exact' => true])->click();
        $fields->getByRole('combobox', ['name' => 'Sort by'])->selectOption('name');
        $show = $fields->getByRole('spinbutton', ['name' => 'Items to show']);
        Expect::locator($show)->toHaveValue('8');
        $show->fill('4');

        // How many products the source yields right now, in the preview, next to "Filled by"
        Expect::locator($fields->getByText('6 items now'))->toBeVisible();

        // The preview shows the first four by name
        self::assertTexts(['Anchor', 'Basic', 'Long sleeve', 'Polo'], $page->frameLocator('iframe')->locator('.cms-tree > .cms-products .sylius-product-name'));

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/t-shirts');
        self::assertTexts(['Anchor', 'Basic', 'Long sleeve', 'Polo'], $shop->locator('.cms-tree > .cms-products > .cms-products__item .sylius-product-name'));
    }
}
