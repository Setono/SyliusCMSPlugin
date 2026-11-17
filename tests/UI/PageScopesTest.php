<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;

/**
 * Editors limit a placement to some shop pages, such as product pages in the Shoes taxon, with its page scope on the
 * snippet's overview page. See "Page scopes" in docs/spec/slots.md and "Placements" in docs/spec/admin.md
 */
final class PageScopesTest extends UITestCase
{
    use Catalog;
    use Placements;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_lets_an_admin_user_scope_a_placement_to_products_in_the_shoes_taxon_and_see_the_snippet_on_a_shoe_and_not_on_a_t_shirt(): void
    {
        $shoes = self::createTaxon('SHOES', 'Shoes');
        self::placeInTaxon(self::createProduct('SNEAKERS', 'Sneakers'), $shoes);
        self::placeInTaxon(self::createProduct('T_SHIRT', 'T-shirt'), self::createTaxon('T_SHIRTS', 'T-shirts'));
        $snippet = self::createSnippet('Shoe care', [self::heading(self::HEADING, 'Shoe care tips')], published: true);
        self::place($snippet, 'product.after_price');
        $this->signInAsAdmin();

        // Before it's scoped, it shows on every product page
        $shop = $this->visit('/en_US/products/t-shirt');
        Expect::locator($shop->locator('.cms-slot--product-after-price .cms-heading'))->toHaveText('Shoe care tips');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $snippet->getUuid()));
        $row = $browser->getByRole('region', ['name' => 'Placements'])->locator('tbody tr');
        Expect::locator($row->locator('[data-page-scope]'))->toContainText('Every page the slot appears on');

        // The editor offers the kinds the product page's slot provides the context of
        $row->getByRole('button', ['name' => 'Edit page scope'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Page scope']);
        $dialog->getByRole('button', ['name' => 'Add condition'])->click();
        $condition = $dialog->getByRole('group', ['name' => 'Condition 1']);
        $kind = $condition->getByRole('combobox', ['name' => 'Kind']);
        Expect::locator($kind)->toHaveValue('product');
        Expect::locator($kind->locator('option[value="taxon"]'))->toHaveAttribute('disabled', '');
        Expect::locator($kind->locator('option[value="entry"]'))->toHaveAttribute('disabled', '');
        Expect::locator($kind->locator('option[value="product_in_taxon"]'))->not()->toHaveAttribute('disabled', '');
        Expect::locator($kind)->toContainText('Taxon (Needs: Taxon page)');
        $condition->getByLabel('Kind')->selectOption('Product in taxon');
        $condition->getByRole('button', ['name' => 'Taxons'])->click();
        $browser->getByRole('combobox', ['name' => 'Search taxons'])->fill('Shoes');
        $browser->getByRole('option', ['name' => 'Shoes'])->click();
        Expect::locator($condition->getByRole('list', ['name' => 'Taxons']))->toContainText('Shoes');
        $dialog->getByRole('button', ['name' => 'Save page scope'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The page scope has been saved.');
        Expect::locator($browser->getByRole('region', ['name' => 'Placements'])->locator('[data-page-scope-condition]'))->toHaveText('Product in taxon is one of: Shoes');

        // The shoe shows it, and the T-shirt doesn't
        $shop = $this->visit('/en_US/products/sneakers');
        Expect::locator($shop->locator('.cms-slot--product-after-price .cms-heading'))->toHaveText('Shoe care tips');
        $shop = $this->visit('/en_US/products/t-shirt');
        Expect::locator($shop->locator('h1'))->toContainText('T-shirt');
        Expect::locator($shop->locator('.cms-slot--product-after-price'))->toHaveCount(0);
    }
}
