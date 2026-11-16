<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * A page can be a channel's homepage, shown at the channel's root URL in place of the store's own homepage. The test
 * app's one channel is "Setono CMS Test Store", whose name Sylius's own homepage has as its title.
 * See "Homepage" in docs/spec/urls-and-seo.md
 */
final class HomepageTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_make_a_page_the_homepage_of_the_default_channel_and_shows_it_at_the_shops_root(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);
        self::assertSame('Setono CMS Test Store', $this->visit('/en_US/')->title());

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $homepageOf = $browser->getByRole('tabpanel', ['name' => 'Settings'])->getByRole('group', ['name' => 'Homepage of']);
        $channel = $homepageOf->getByRole('checkbox', ['name' => 'Setono CMS Test Store']);
        Expect::locator($channel)->not()->toBeChecked();

        $channel->check();

        Expect::locator($browser->getByRole('complementary')->getByText('Saved', ['exact' => true]))->toBeVisible();
        Expect::locator($channel)->toBeChecked();
        // The builder previews it at the channel's root, where shoppers see it
        $browser->waitForFunction('() => /\/en_US\/\?_cms_preview=/.test(document.querySelector(\'iframe[title="Preview"]\')?.getAttribute("src") ?? "")');
        Expect::locator($browser->frameLocator('iframe[title="Preview"]')->locator('.cms-tree .cms-heading'))->toHaveText('Hello shoppers');

        $shop = $this->visit('/en_US/');

        self::assertSame('Welcome', $shop->title());
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello shoppers');
        self::assertSame($this->getBaseUrl() . '/en_US/', $shop->locator('link[rel="canonical"]')->getAttribute('href'));

        // The page's own URL takes shoppers to the shop's root
        $shop = $this->visit('/en_US/welcome');

        self::assertSame($this->getBaseUrl() . '/en_US/', $shop->url());
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello shoppers');
    }

    /**
     * @test
     */
    public function its_overview_page_shows_which_page_is_the_channels_homepage(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('Welcome', 'welcome', published: true);
        self::setHomepageOf($page, 'WEB');
        $other = self::createPage('About us', 'about-us', published: true);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $other->getUuid()));
        $homepages = $browser->getByRole('table', ['name' => 'Homepage of']);

        Expect::locator($homepages->getByRole('row', ['name' => 'Setono CMS Test Store Welcome']))->toBeVisible();
        $homepages->getByRole('link', ['name' => 'Welcome'])->click();

        Expect::locator($browser->locator('[data-overview-header] [data-title]'))->toHaveText('Welcome');
        $row = $browser->getByRole('table', ['name' => 'Homepage of'])->getByRole('row', ['name' => 'Setono CMS Test Store This page']);
        Expect::locator($row)->toBeVisible();
        self::assertSame('WEB', $row->getAttribute('data-channel'));
    }
}
