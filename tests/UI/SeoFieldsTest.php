<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * The SEO fields on the builder's URL & SEO tab, and the head tags they give the shop page once published.
 * See "SEO fields" and "Head tags" in docs/spec/urls-and-seo.md
 */
final class SeoFieldsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_fill_in_the_meta_title_hide_the_page_from_search_engines_and_publish_it(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        $shop = $this->visit('/en_US/about-us');
        self::assertSame('About us', $shop->title(), 'Without a meta title, the page is titled with its title');
        Expect::locator($shop->locator('meta[name="robots"]'))->toHaveCount(0);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('tab', ['name' => 'URL & SEO'])->click();
        $panel = $browser->getByRole('tabpanel', ['name' => 'URL & SEO']);
        $panel->locator('#cms-entry-metaTitle')->fill('About our shop | Setono');
        $panel->getByRole('checkbox', ['name' => 'Hide from search engines'])->check();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        self::assertTrue($panel->getByRole('checkbox', ['name' => 'Hide from search engines'])->isChecked());

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/about-us');

        self::assertSame('About our shop | Setono', $shop->title());
        Expect::locator($shop->locator('meta[name="robots"]'))->toHaveAttribute('content', 'noindex');
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
    }
}
