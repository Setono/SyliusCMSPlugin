<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * Creating an entry through the create dialog, which opens the builder, and the entry's overview page, which the
 * builder goes back to. See "Create dialog", "Into and out of the builder" and "Entry overview page" in
 * docs/spec/admin.md
 */
final class CreateEntryTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_creates_a_page_through_the_dialog_leaves_the_builder_with_esc_and_publishes_it_from_its_overview_page(): void
    {
        $this->signInAsAdmin();

        // From All entries, the dialog asks for the content type first
        $browser = $this->visit('/admin/cms/entries');
        $browser->getByRole('button', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog');
        Expect::locator($dialog)->toHaveAccessibleName('New entry What do you want to create?');
        $dialog->getByRole('link', ['name' => 'Page'])->click();

        // The slug follows the title. A store with one channel has no channels to pick
        Expect::locator($dialog)->toHaveAccessibleName('New Page');
        $dialog->getByLabel('Title')->fill('About us');
        Expect::locator($dialog->getByLabel('Slug'))->toHaveValue('about-us');
        Expect::locator($dialog->getByRole('group', ['name' => 'Channels']))->toHaveCount(0);
        $dialog->getByRole('button', ['name' => 'Create'])->click();

        // The builder opens with the new page, which was never published
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About us');
        Expect::locator($browser->getByText('Never published', ['exact' => true]))->toBeVisible();
        $page = self::page('about-us');
        self::assertFalse($page->isPublished());
        self::assertSame(['WEB'], $page->getChannelCodes());

        // Esc leaves the builder for the page's overview page
        $browser->keyboard()->press('Escape');

        $header = $browser->locator('[data-overview-header]');
        Expect::locator($header->getByRole('heading', ['level' => 1]))->toContainText('About us');
        Expect::locator($header)->toContainText('/en_US/about-us');
        Expect::locator($header->locator('[data-status] .label'))->toHaveText('Never published');
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s', $page->getUuid()), $browser->url());

        // Publishing from there makes it live
        $publishing = $browser->getByRole('region', ['name' => 'Publishing']);
        $publishing->getByRole('button', ['name' => 'Publish'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The entry has been published.');
        Expect::locator($header->locator('[data-status] .label'))->toHaveText('Published');
        Expect::locator($browser->getByRole('region', ['name' => 'Publishing'])->getByRole('button', ['name' => 'Publish']))->toHaveCount(0);
        self::assertTrue(self::page('about-us')->isPublished());

        $this->visit('/en_US/about-us');
        self::assertSame(200, $this->getLastResponse()?->getStatusCode());
    }

    /**
     * @test
     */
    public function the_slug_follows_the_title_until_the_editor_changes_it_and_the_channels_start_all_picked(): void
    {
        $this->signInAsAdmin();
        self::createChannel('B2B', 'Wholesale');

        // A content type's list opens the dialog at its form
        $browser = $this->visit('/admin/cms/entries/page');
        $browser->getByRole('link', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'New Page']);
        Expect::locator($dialog->getByLabel('Title'))->toBeVisible();
        // The title takes the focus. playwright-php's toBeFocused() doesn't see it, so the page is asked
        self::assertSame('setono_sylius_cms_create_entry_title', $browser->evaluate('() => document.activeElement?.id'));

        $channels = $dialog->getByRole('group', ['name' => 'Channels']);
        Expect::locator($channels->getByRole('checkbox', ['name' => 'Setono CMS Test Store']))->toBeChecked();
        Expect::locator($channels->getByRole('checkbox', ['name' => 'Wholesale']))->toBeChecked();

        $dialog->getByLabel('Title')->fill('About us');
        Expect::locator($dialog->getByLabel('Slug'))->toHaveValue('about-us');
        $dialog->getByLabel('Slug')->fill('company/about');
        $dialog->getByLabel('Title')->fill('About our shop');
        // Long enough for the slug to have followed the title, had it still followed it
        $browser->waitForFunction('() => new Promise((resolve) => setTimeout(() => resolve(true), 600))');
        Expect::locator($dialog->getByLabel('Slug'))->toHaveValue('company/about');

        $channels->getByRole('checkbox', ['name' => 'Wholesale'])->uncheck();
        $dialog->getByRole('button', ['name' => 'Create'])->click();

        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About our shop');
        $page = self::page('company/about');
        self::assertSame('About our shop', $page->getTranslation('en_US')->getTitle());
        self::assertSame(['WEB'], $page->getChannelCodes());
    }

    private static function page(string $slug): PageInterface
    {
        /** @var RepositoryInterface<PageInterface> $pages */
        $pages = self::getContainer()->get('setono_sylius_cms.repository.page');
        foreach ($pages->findAll() as $page) {
            if ($page->getTranslation('en_US')->getSlug() === $slug) {
                return $page;
            }
        }

        self::fail(sprintf('There is no page with the slug "%s"', $slug));
    }
}
