<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface as BrowserPage;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;

/**
 * The on-save settings in the builder's Settings tab take effect on save, outside the draft.
 * See "What takes effect on save" in docs/spec/publishing.md
 */
final class EntrySettingsTest extends UITestCase
{
    use Customers;

    /**
     * @test
     */
    public function an_admin_user_switches_a_published_page_off_and_the_shop_answers_404_without_a_publish(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::assertSame(200, $this->statusOf('/en_US/about-us'));

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $settings = $browser->getByRole('tabpanel', ['name' => 'Settings']);
        Expect::locator($settings->getByText('Takes effect when saved, without publishing')->first())->toBeVisible();
        Expect::locator($browser->getByRole('group', ['name' => 'Channels']))->toHaveCount(0);
        $enabled = $settings->getByRole('checkbox', ['name' => 'Enabled']);
        Expect::locator($enabled)->toBeChecked();

        $enabled->uncheck();

        $this->expectSaved($browser);
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
        self::assertSame(404, $this->statusOf('/en_US/about-us'));

        // Turning it back on shows the published content it kept
        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $enabled = $browser->getByRole('checkbox', ['name' => 'Enabled']);
        Expect::locator($enabled)->not()->toBeChecked();
        $enabled->check();
        $this->expectSaved($browser);

        $browser = $this->visit('/en_US/about-us');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
    }

    /**
     * @test
     */
    public function an_admin_user_shows_a_page_only_to_wholesale_customers(): void
    {
        $this->signInAsAdmin();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $wholesaler = self::createShopUser('wholesaler@example.com', 'wholesale');
        $page = self::createPage('Prices', 'prices', 'Wholesale prices', published: true);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('combobox', ['name' => 'Audience'])->selectOption('only');
        $this->expectSaved($browser);
        $browser->getByRole('group', ['name' => 'Customer groups'])->getByRole('checkbox', ['name' => 'Wholesale'])->check();
        $this->expectSaved($browser);

        // A guest gets a 404, with no redirect to sign in
        self::assertSame(404, $this->statusOf('/en_US/prices'));

        $this->loginUser($wholesaler, 'shop');
        $browser = $this->visit('/en_US/prices');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Wholesale prices');
    }

    private function expectSaved(BrowserPage $browser): void
    {
        Expect::locator($browser->getByRole('complementary')->getByText('Saved', ['exact' => true]))->toBeVisible();
    }

    /**
     * The status the shop answers a URL with, as the browser gets it
     */
    private function statusOf(string $path): ?int
    {
        return $this->getPage()->goto($this->getBaseUrl() . $path)?->status();
    }
}
