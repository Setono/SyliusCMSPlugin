<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface as BrowserPage;

/**
 * The slug in the builder's URL & SEO tab: a renamed page's old URL redirects once it's published, and a slug another
 * route takes isn't saved. See "Slugs", "Reserved URLs" and "Old slugs" in docs/spec/urls-and-seo.md
 */
final class SlugsTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_renames_a_published_pages_slug_publishes_it_and_its_old_url_lands_on_the_new_one(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $panel = $this->openUrlTab($browser);
        $slug = $panel->getByLabel('Slug');
        Expect::locator($slug)->toHaveValue('about-us');

        $slug->fill('about-our-shop');
        Expect::locator($panel->locator('#cms-entry-slug-help'))->toContainText('The URL: /en_US/about-our-shop');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // The tab lists the old URL, which now redirects
        $oldUrls = $panel->getByRole('list', ['name' => 'Redirects from']);
        Expect::locator($oldUrls->getByRole('listitem'))->toHaveCount(1);
        self::assertSame('/en_US/about-us', trim($oldUrls->getByRole('listitem')->textContent() ?? ''));

        $browser = $this->visit('/en_US/about-us');

        self::assertStringEndsWith('/en_US/about-our-shop', $browser->url());
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
    }

    /**
     * @test
     */
    public function an_admin_user_types_a_slug_a_sylius_route_takes_and_sees_why_on_the_field_while_the_last_saved_one_is_kept(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $panel = $this->openUrlTab($browser);
        $slug = $panel->getByLabel('Slug');

        $slug->fill('cart');

        // The error is the field's, which describes it
        $errors = $panel->locator('#cms-entry-slug-errors');
        Expect::locator($errors)->toHaveText('The URL /en_US/cart is taken by the route sylius_shop_cart_summary.');
        self::assertContains('cms-entry-slug-errors', explode(' ', (string) $panel->locator('#cms-entry-slug')->getAttribute('aria-describedby')));
        Expect::locator($slug)->toHaveValue('cart');

        // The server kept the slug it had
        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        Expect::locator($this->openUrlTab($browser)->getByLabel('Slug'))->toHaveValue('about-us');
    }

    private function openUrlTab(BrowserPage $browser): LocatorInterface
    {
        $browser->getByRole('tab', ['name' => 'URL & SEO'])->click();

        return $browser->getByRole('tabpanel', ['name' => 'URL & SEO']);
    }
}
