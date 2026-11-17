<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\DevKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;

/**
 * In the test application's dev environment, the web profiler's toolbar leads to the CMS panel, which lists the slots
 * rendered on the page and why each placement showed or didn't. See "Profiler panel" in docs/spec/rendering.md
 */
final class ProfilerPanelTest extends UITestCase
{
    use Catalog;
    use Placements;

    protected static function getKernelClass(): string
    {
        return DevKernel::class;
    }

    /**
     * @test
     */
    public function it_lets_a_developer_open_the_cms_panel_from_the_toolbar_of_a_product_page_and_see_its_slots(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');
        self::place(self::createSnippet('Draft', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Draft')]), 'product.after_price');
        self::place(self::createSnippet('Size guide', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Size guide')], published: true, context: ['product']), 'product.after_price');

        $page = $this->visit('/en_US/products/t-shirt');
        Expect::locator($page->locator('.cms-slot--product-after-price .cms-heading'))->toHaveText('Size guide');

        $toolbarItem = $page->locator('.sf-toolbar-block-setono_sylius_cms');
        Expect::locator($toolbarItem)->toBeVisible();
        $toolbarItem->locator('a')->click();

        Expect::locator($page->getByRole('heading', ['name' => 'CMS slots']))->toBeVisible();
        $slots = $page->locator('[data-cms-slot]');
        Expect::locator($slots->filter(['hasText' => 'shop.footer']))->toHaveCount(1);

        $afterPrice = $page->locator('[data-cms-slot="product.after_price"]');
        Expect::locator($afterPrice->locator('h3'))->toContainText('Product page: under the price');
        Expect::locator($afterPrice->locator('[data-context-key="product"] [data-from]'))->toHaveText('The template event');
        $candidates = $afterPrice->locator('[data-cms-candidate]');
        self::assertTexts(['Draft', 'Size guide'], $candidates->locator('[data-snippet-title]'));
        self::assertTexts(['Its snippet isn\'t published', 'Shows'], $candidates->locator('[data-why]'));
    }
}
