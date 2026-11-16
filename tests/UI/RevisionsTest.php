<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface as BrowserPage;

/**
 * Every publish stores a revision, which editors preview read-only from the entry's overview page and restore into the
 * draft. See "Revisions" in docs/spec/publishing.md and "Entry overview page" in docs/spec/admin.md
 */
final class RevisionsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_editor_preview_an_earlier_revision_restore_it_into_the_draft_and_publish_it(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');

        // Two publishes with different headings
        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $this->publish($browser);
        $this->editHeading($browser, 'Hello world');
        $this->publish($browser);

        // The overview page lists both, newest first
        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $rows = $browser->getByRole('region', ['name' => 'Revisions'])->locator('tbody tr');
        Expect::locator($rows)->toHaveCount(2);
        Expect::locator($rows->nth(0))->toContainText('John Doe');
        $first = $rows->nth(1);
        Expect::locator($first)->toContainText('John Doe');

        // The first one's preview opens read-only in the shop, in a new tab. playwright-symfony serves only the test's own
        // page from the kernel, so the test opens the link's URL there
        $link = $first->getByRole('link', ['name' => 'Preview']);
        self::assertSame('_blank', $link->getAttribute('target'));
        $preview = $this->visit((string) $link->getAttribute('href'));
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
        self::assertStringContainsString('/en_US/about-us?_cms_preview=', $preview->url());
        self::assertSame(0, $preview->locator('script[data-cms-bridge]')->count(), 'A revision\'s preview gets no bridge');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $first = $browser->getByRole('region', ['name' => 'Revisions'])->locator('tbody tr')->nth(1);

        // Restoring it asks first, then puts it in the draft
        $first->getByRole('button', ['name' => 'Restore'])->click();
        $browser->locator('#confirmation-modal')->getByText('Yes')->click();
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The revision has been restored into the draft.');

        // The builder has it as unpublished changes, and publishing makes it live again
        $browser->getByRole('link', ['name' => 'Open in builder'])->click();
        Expect::locator($browser->getByText('Unpublished changes', ['exact' => true]))->toBeVisible();
        $browser->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        Expect::locator($browser->getByLabel('Text'))->toHaveValue('Hello');
        $this->publish($browser);

        $browser = $this->visit('/en_US/about-us');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
    }

    /**
     * Publishes from the builder's top bar, and waits until the status labels say it's done
     */
    private function publish(BrowserPage $browser): void
    {
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByText('Never published', ['exact' => true]))->toHaveCount(0);
        Expect::locator($browser->getByText('Unpublished changes', ['exact' => true]))->toHaveCount(0);
    }

    private function editHeading(BrowserPage $browser, string $text): void
    {
        $browser->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        $browser->getByLabel('Text')->fill($text);
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
    }
}
