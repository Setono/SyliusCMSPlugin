<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface as BrowserPage;

final class DraftsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_edit_a_published_page_in_a_draft_publish_it_and_discard_later_changes(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $builderUrl = sprintf('/admin/cms/entry/%s/builder', $page->getUuid());

        // Editing a published page goes to its draft
        $browser = $this->visit($builderUrl);
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByRole('button', ['name' => 'Discard changes']))->toHaveCount(0);

        $this->editHeading($browser, 'Hello world');
        Expect::locator($browser->getByText('Unpublished changes', ['exact' => true]))->toBeVisible();

        // The shop still shows the published text
        $browser = $this->visit('/en_US/about-us');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello');

        // Publishing makes the draft what shoppers see
        $browser = $this->visit($builderUrl);
        Expect::locator($browser->getByText('Unpublished changes', ['exact' => true]))->toBeVisible();
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByRole('button', ['name' => 'Discard changes']))->toHaveCount(0);

        $browser = $this->visit('/en_US/about-us');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello world');

        // Discarding later changes brings the builder back to the published text
        $browser = $this->visit($builderUrl);
        $this->editHeading($browser, 'Goodbye');
        Expect::locator($browser->getByText('Unpublished changes', ['exact' => true]))->toBeVisible();

        $browser->getByRole('button', ['name' => 'Discard changes'])->click();

        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByLabel('Text'))->toHaveValue('Hello world');
        Expect::locator($browser->getByRole('button', ['name' => 'Heading Hello world']))->toBeVisible();
        Expect::locator($browser->getByRole('button', ['name' => 'Discard changes']))->toHaveCount(0);

        $browser = $this->visit('/en_US/about-us');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello world');
    }

    private function editHeading(BrowserPage $browser, string $text): void
    {
        $browser->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        $browser->getByLabel('Text')->fill($text);
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
    }
}
