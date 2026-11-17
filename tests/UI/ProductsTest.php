<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * The Products block: an editor hand-picks products with the search picker and reorders them, and shoppers see the
 * shop's own product cards in that order. See "Products" in docs/spec/blocks.md and "List fields" in
 * docs/spec/sources.md
 */
final class ProductsTest extends UITestCase
{
    use Catalog;

    /**
     * @test
     */
    public function it_lets_an_admin_user_pick_three_products_and_reorder_them_and_shoppers_see_the_shop_s_product_cards_in_that_order(): void
    {
        self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap');
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('HOODIE', 'Hoodie');
        $this->signInAsAdmin();
        $entry = self::createPage('Shop now', 'shop-now');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Products', 'exact' => true])->click();

        // Each product is found with the search and added to the end of the list
        $fields = $page->getByRole('complementary');
        Expect::locator($fields->getByText('Nothing chosen'))->toBeVisible();
        self::pick($page, $fields, 'mug', 'Mug');
        self::pick($page, $fields, 'shirt', 'T-shirt');
        self::pick($page, $fields, 'cap', 'Cap');

        $picked = $fields->getByRole('list', ['name' => 'Products'])->getByRole('listitem');
        self::assertTexts(['Mug', 'T-shirt', 'Cap'], $picked);

        // Cap goes first, and T-shirt last
        $picked->nth(2)->getByRole('button', ['name' => 'Move up'])->click();
        self::assertTexts(['Mug', 'Cap', 'T-shirt'], $picked);
        $picked->nth(1)->getByRole('button', ['name' => 'Move up'])->click();
        self::assertTexts(['Cap', 'Mug', 'T-shirt'], $picked);

        // The preview shows the shop's product cards in that order before it's published
        self::assertTexts(['Cap', 'Mug', 'T-shirt'], $page->frameLocator('iframe')->locator('.cms-tree > .cms-products .sylius-product-name'));

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/shop-now');
        $cards = $shop->locator('.cms-tree > .cms-products.cms-products--grid > .cms-products__item');
        self::assertTexts(['Cap', 'Mug', 'T-shirt'], $cards->locator('.sylius-product-name'));

        $cards->nth(1)->locator('a.sylius-product-name')->click();

        Expect::locator($shop->locator('h1'))->toContainText('Mug');
        self::assertStringEndsWith('/en_US/products/mug', $shop->url());
    }

    /**
     * Opens the Products field's search with its add button, and picks what it finds. The field's label names its
     * "Filled by" select, since sources can fill it too
     */
    private static function pick(PageInterface $page, LocatorInterface $fields, string $query, string $name): void
    {
        $fields->getByRole('button', ['name' => 'Add product', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Search products'])->fill($query);
        $page->getByRole('option', ['name' => $name, 'exact' => true])->click();
        Expect::locator($page->getByRole('combobox', ['name' => 'Search products']))->toHaveCount(0);
    }

    /**
     * Expect::toHaveText() with a list needs allTextContents(), which playwright-php's server doesn't know, so each
     * element is checked on its own
     *
     * @param list<string> $texts
     */
    private static function assertTexts(array $texts, LocatorInterface $locator): void
    {
        Expect::locator($locator)->toHaveCount(count($texts));
        foreach ($texts as $index => $text) {
            Expect::locator($locator->nth($index))->toHaveText($text);
        }
    }
}
