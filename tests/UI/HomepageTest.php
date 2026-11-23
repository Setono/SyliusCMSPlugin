<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaverInterface;

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

        self::assertSame('Welcome | Setono CMS Test Store', $shop->title());
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello shoppers');
        self::assertSame($this->getBaseUrl() . '/en_US/', $shop->locator('link[rel="canonical"]')->getAttribute('href'));

        // The page's own URL takes shoppers to the shop's root
        $shop = $this->visit('/en_US/welcome');

        self::assertSame($this->getBaseUrl() . '/en_US/', $shop->url());
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello shoppers');
    }

    /**
     * A page can only be the homepage of a channel it's in, so "Homepage of" lists only those, and follows the channels
     * picked above it
     *
     * @test
     */
    public function it_lists_only_the_pages_channels_to_make_it_the_homepage_of(): void
    {
        $this->signInAsAdmin();
        self::createChannel('B2B', 'Wholesale store');
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', channels: ['WEB']);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $settings = $browser->getByRole('tabpanel', ['name' => 'Settings']);
        $homepageOf = $settings->getByRole('group', ['name' => 'Homepage of']);

        Expect::locator($homepageOf->getByRole('checkbox'))->toHaveCount(1);
        Expect::locator($homepageOf->getByRole('checkbox', ['name' => 'Setono CMS Test Store']))->toBeVisible();

        $settings->getByRole('group', ['name' => 'Channels'])->getByRole('checkbox', ['name' => 'Wholesale store'])->check();
        Expect::locator($browser->getByRole('complementary')->getByText('Saved', ['exact' => true]))->toBeVisible();

        Expect::locator($homepageOf->getByRole('checkbox'))->toHaveCount(2);
        $wholesale = $homepageOf->getByRole('checkbox', ['name' => 'Wholesale store']);
        $wholesale->check();

        Expect::locator($browser->getByRole('complementary')->getByText('Saved', ['exact' => true]))->toBeVisible();
        Expect::locator($wholesale)->toBeChecked();
        self::assertSame(['B2B'], self::reloadPage($page)->getHomepageChannelCodes());
    }

    /**
     * A page that has left a channel it's the homepage of stays its homepage, which the overview page warns about.
     * "Homepage of" still lists the channel, ticked and saying the page isn't in it, so it can be taken out there. See
     * "Homepage" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function it_warns_about_a_page_that_left_a_channel_it_is_the_homepage_of_and_lets_an_admin_user_take_the_channel_out(): void
    {
        $this->signInAsAdmin();
        self::createChannel('B2B', 'Wholesale store');
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true, channels: ['WEB', 'B2B']);
        self::setHomepageOf($page, 'B2B');
        self::leaveChannels($page, 'B2B');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $problems = $browser->getByRole('region', ['name' => 'Problems']);
        self::assertTexts(
            ['It\'s the homepage of Wholesale store, but isn\'t in that channel, so shoppers there see the store\'s own homepage. Put it back in the channel, or take the channel out of "Homepage of".'],
            $problems->locator('[data-problem="homepage_not_in_channel"] .description'),
        );
        Expect::locator($problems->locator('[data-problem="homepage_not_in_channel"] .header'))->toHaveText('Warning');

        $problems->locator('[data-problem="homepage_not_in_channel"]')->getByRole('link', ['name' => 'Fix in builder'])->click();
        $homepageOf = $browser->getByRole('tabpanel', ['name' => 'Settings'])->getByRole('group', ['name' => 'Homepage of']);
        $left = $homepageOf->getByRole('checkbox', ['name' => 'Wholesale store (the page isn\'t in this channel)']);
        Expect::locator($homepageOf->getByRole('checkbox'))->toHaveCount(2);
        Expect::locator($left)->toBeChecked();
        Expect::locator($homepageOf->getByRole('checkbox', ['name' => 'Setono CMS Test Store', 'exact' => true]))->not()->toBeChecked();

        // A click, as the box goes away once the save has taken the channel out
        $left->click();

        Expect::locator($browser->getByRole('complementary')->getByText('Saved', ['exact' => true]))->toBeVisible();
        // Once taken out, it's no longer listed, as the page isn't in the channel
        Expect::locator($homepageOf->getByRole('checkbox'))->toHaveCount(1);
        self::assertSame([], self::reloadPage($page)->getHomepageChannelCodes());

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        Expect::locator($browser->locator('[data-problem="homepage_not_in_channel"]'))->toHaveCount(0);
    }

    /**
     * @test
     */
    public function it_shows_which_page_is_the_channels_homepage_on_its_overview_page(): void
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
        // It shows at the channel's root, where its own URL redirects
        $url = $browser->getByRole('region', ['name' => 'URLs'])->locator('tr[data-locale="en_US"]');
        Expect::locator($url->locator('[data-homepage-url] code'))->toHaveText('/en_US/');
        Expect::locator($url->locator('[data-url]'))->toHaveText('/en_US/welcome');
    }

    /**
     * Takes the page out of the channels as saving its settings does, which leaves it the homepage of those it was
     */
    private static function leaveChannels(PageInterface $page, string ...$channelCodes): void
    {
        $current = EntrySettings::of($page);

        /** @var EntrySettingsSaverInterface $saver */
        $saver = self::getContainer()->get(EntrySettingsSaverInterface::class);
        $saver->save($page, new EntrySettings($current->enabled, $current->dateWindow, array_values(array_diff($current->channels, $channelCodes)), $current->audience, $current->homepageOf));
    }
}
