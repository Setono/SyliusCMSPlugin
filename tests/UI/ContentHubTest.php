<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface as BrowserPage;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * The Content item opens the hub, whose lists show each entry's status. See "Navigation" and "Entry lists" in
 * docs/spec/admin.md
 */
final class ContentHubTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_open_content_find_pages_by_status_and_delete_one(): void
    {
        $this->signInAsAdmin();
        $about = self::createPage('About us', 'about-us', published: true);
        $contact = self::createPage('Contact', 'contact');

        // Content opens the hub, and its menu opens the pages
        $browser = $this->visit('/admin/');
        $browser->locator('.sylius-admin-menu')->getByRole('link', ['name' => 'Content'])->click();
        Expect::locator($browser->getByRole('heading', ['name' => 'Content', 'level' => 1]))->toBeVisible();
        $hubMenu = $browser->getByRole('navigation', ['name' => 'Content']);
        Expect::locator($hubMenu->getByRole('link', ['name' => 'Pages']))->toContainText('2');
        $hubMenu->getByRole('link', ['name' => 'Pages'])->click();

        Expect::locator($browser->getByRole('heading', ['name' => 'Pages', 'level' => 1]))->toBeVisible();
        Expect::locator($hubMenu->getByRole('link', ['name' => 'Pages']))->toHaveAttribute('aria-current', 'page');
        Expect::locator(self::row($browser, 'About us'))->toContainText('/en_US/about-us');
        Expect::locator(self::statusLabels($browser, 'About us'))->toHaveCount(1);
        Expect::locator(self::statusLabels($browser, 'About us'))->toHaveText('Published');
        Expect::locator(self::statusLabels($browser, 'Contact'))->toHaveCount(1);
        Expect::locator(self::statusLabels($browser, 'Contact'))->toHaveText('Never published');
        // Published is Semantic UI's green label
        self::assertSame('rgb(33, 186, 69)', self::statusLabels($browser, 'About us')->evaluate('label => getComputedStyle(label).backgroundColor'));

        // Filtering by Never published leaves the page that was never published
        $browser->getByLabel('Status')->selectOption('Never published');
        $browser->getByRole('button', ['name' => 'Filter'])->click();
        Expect::locator($browser->locator('tbody tr'))->toHaveCount(1);
        Expect::locator(self::row($browser, 'Contact'))->toBeVisible();
        Expect::locator($browser->getByLabel('Status'))->toHaveValue('never_published');

        // Deleting it asks first, then goes back to the filtered list, which is empty now
        self::row($browser, 'Contact')->getByRole('button', ['name' => 'Delete'])->click();
        $browser->locator('#confirmation-modal')->getByText('Yes')->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The entry has been deleted.');
        Expect::locator($browser->locator('tbody tr'))->toHaveCount(0);
        Expect::locator($browser->getByLabel('Status'))->toHaveValue('never_published');
        /** @var RepositoryInterface<PageInterface> $pages */
        $pages = self::getContainer()->get('setono_sylius_cms.repository.page');
        self::assertNull($pages->findOneBy(['uuid' => $contact->getUuid()]));
        self::assertNotNull($pages->findOneBy(['uuid' => $about->getUuid()]));
    }

    private static function row(BrowserPage $browser, string $title): LocatorInterface
    {
        return $browser->locator('tbody tr')->filter(['hasText' => $title]);
    }

    private static function statusLabels(BrowserPage $browser, string $title): LocatorInterface
    {
        return self::row($browser, $title)->locator('[data-column="status"] .label');
    }
}
