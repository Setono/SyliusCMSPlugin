<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;

/**
 * The entry overview page, between an entry's list and the builder, and the ways between them.
 * See "Entry overview page" and "Into and out of the builder" in docs/spec/admin.md
 */
final class EntryOverviewTest extends UITestCase
{
    /**
     * @test
     */
    public function a_row_opens_the_overview_page_which_discards_the_unpublished_changes_after_asking(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-our-shop');

        $browser = $this->visit('/admin/cms/entries/page');
        $browser->locator('tbody')->getByRole('link', ['name' => 'About our shop'])->click();

        $header = $browser->locator('[data-overview-header]');
        Expect::locator($header->getByRole('heading', ['level' => 1]))->toContainText('About our shop');
        Expect::locator($header->locator('[data-status] .label'))->toHaveCount(2);
        $urls = $browser->getByRole('region', ['name' => 'URLs']);
        Expect::locator($urls)->toContainText('/en_US/about-us');
        Expect::locator($urls)->toContainText('After publishing: /en_US/about-our-shop');

        // Discarding asks first, then the overview page shows what's published
        $publishing = $browser->getByRole('region', ['name' => 'Publishing']);
        Expect::locator($publishing)->toContainText('Unpublished changes.');
        $publishing->getByRole('button', ['name' => 'Discard changes'])->click();
        $browser->locator('#confirmation-modal')->getByText('Yes')->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The unpublished changes have been discarded.');
        Expect::locator($browser->locator('[data-overview-header]')->getByRole('heading', ['level' => 1]))->toContainText('About us');
        Expect::locator($browser->getByRole('region', ['name' => 'URLs']))->not()->toContainText('After publishing');
        self::assertNull(self::draftOf($page));
    }

    /**
     * @test
     */
    public function the_builder_opens_from_the_overview_page_and_its_back_button_and_more_tab_lead_back_to_it(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $browser->getByRole('link', ['name' => 'Open in builder'])->click();
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About us');

        // The settings' More tab links to the overview page
        $browser->getByRole('tab', ['name' => 'More'])->click();
        $browser->getByRole('link', ['name' => 'Open the overview page'])->click();
        Expect::locator($browser->locator('[data-overview-header]'))->toContainText('About us');

        // And Back returns there too
        $browser->getByRole('link', ['name' => 'Open in builder'])->click();
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About us');
        $browser->getByRole('link', ['name' => 'Back'])->click();
        Expect::locator($browser->locator('[data-overview-header]'))->toContainText('About us');
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s', $page->getUuid()), $browser->url());
    }

    /**
     * Saves a draft of the published page with this title and slug, as the builder's autosave would
     */
    private static function saveDraft(PageInterface $page, string $title, string $slug): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle($title);
        $workingCopy->setSlug($slug);
        $workingCopies->save($page, $workingCopy);
    }
}
