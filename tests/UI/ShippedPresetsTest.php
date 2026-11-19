<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Bestsellers;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * An editor creates a page from the plugin's shipped Landing page starting point in the create dialog, and finds its
 * banner carousel, bestsellers carousel, USPs and rich text in the builder's preview, with the shipped texts and a
 * placeholder for every image, as the shipped presets' images are empty. See "Shipped code presets" in
 * docs/spec/layouts-and-presets.md and "Empty images" in docs/spec/blocks.md
 */
final class ShippedPresetsTest extends UITestCase
{
    use Bestsellers;
    use Catalog;

    /**
     * @test
     */
    public function it_lets_an_admin_user_create_a_page_from_the_landing_page_starting_point_and_see_its_blocks_with_image_placeholders_in_the_preview(): void
    {
        self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap');
        self::scoreProducts(['MUG' => 5, 'CAP' => 3]);
        $this->signInAsAdmin();

        // The shipped starting points are offered with their thumbnails, and Blank is still picked to start with
        $page = $this->visit('/admin/cms/entries/page');
        $page->getByRole('link', ['name' => 'Create'])->click();
        $create = $page->getByRole('dialog', ['name' => 'New Page']);
        $startingPoints = $create->getByRole('group', ['name' => 'Starting point']);
        Expect::locator($startingPoints->getByRole('radio', ['name' => 'Blank']))->toBeChecked();
        Expect::locator($startingPoints->locator('img[src="/bundles/setonosyliuscmsplugin/thumbnail/preset/landing_page.svg"]'))->toBeVisible();
        Expect::locator($startingPoints->locator('img[src="/bundles/setonosyliuscmsplugin/thumbnail/preset/content_page.svg"]'))->toBeVisible();
        $startingPoints->getByText('Landing page', ['exact' => true])->click();
        Expect::locator($startingPoints->getByRole('radio', ['name' => 'Landing page']))->toBeChecked();
        $create->getByLabel('Title')->fill('Spring');
        $create->getByRole('button', ['name' => 'Create'])->click();

        Expect::locator($page->getByRole('heading', ['level' => 1]))->toHaveText('Spring');
        $preview = $page->frameLocator('iframe[title="Preview"]');

        // The banner carousel, each banner with the image placeholder
        $banners = $preview->locator('.cms-tree > .cms-carousel .cms-carousel__slide > .cms-banner');
        self::assertTexts(['New arrivals', 'Bestsellers', 'Sale'], $banners->locator('.cms-banner__heading'));
        Expect::locator($banners->locator('.cms-image-placeholder.cms-banner__media'))->toHaveCount(3);

        // The bestsellers carousel
        $bestsellers = $preview->locator('.cms-tree > .cms-products.cms-products--carousel .cms-products__item');
        Expect::locator($bestsellers)->toHaveCount(2);
        Expect::locator($bestsellers->first())->toContainText('Mug');

        // The three USPs, each image a placeholder
        $usps = $preview->locator('.cms-tree > .cms-columns > .cms-column');
        self::assertTexts(['Free delivery', 'Easy returns', 'Secure payment'], $usps->locator('h3.cms-heading'));
        Expect::locator($usps->locator('figure.cms-image > .cms-image-placeholder'))->toHaveCount(3);

        // The rich text, and no title, as the landing page hides it
        Expect::locator($preview->locator('.cms-tree > .cms-rich-text h2'))->toHaveText('Why shop with us');
        Expect::locator($preview->locator('h1:has-text("Spring")'))->toHaveCount(0);
    }
}
