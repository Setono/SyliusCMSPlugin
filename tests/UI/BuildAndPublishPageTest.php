<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

final class BuildAndPublishPageTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_creates_a_page_adds_a_heading_publishes_it_and_sees_it_in_the_shop(): void
    {
        $this->signInAsAdmin();

        $page = $this->visit('/admin/cms/entries/page/new');
        $page->getByLabel('Title')->fill('About us');
        $page->getByLabel('Slug')->fill('about-us');
        $page->getByRole('button', ['name' => 'Create'])->click();

        // The builder opens with the new page
        Expect::locator($page->getByRole('heading', ['level' => 1]))->toHaveText('About us');
        Expect::locator($page->getByText('Never published', ['exact' => true]))->toBeVisible();

        $page->getByRole('button', ['name' => 'Add block'])->click();
        $page->getByRole('menuitem', ['name' => 'Heading'])->click();
        $page->getByLabel('Text')->fill('Hello');

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');

        $page->getByRole('button', ['name' => 'Publish'])->click();

        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $page = $this->visit('/en_US/about-us');

        self::assertSame(200, $this->getLastResponse()?->getStatusCode());
        self::assertSame('<h2 class="cms-heading">Hello</h2>', $page->locator('.cms-tree .cms-heading')->evaluate('element => element.outerHTML'));
    }
}
