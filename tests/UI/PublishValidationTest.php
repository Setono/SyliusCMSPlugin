<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface as BrowserPage;

final class PublishValidationTest extends UITestCase
{
    /**
     * @test
     */
    public function publish_refuses_an_empty_heading_points_at_it_and_publishes_once_it_is_filled_in(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);

        // An empty heading saves, and the tree flags it
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Heading', 'exact' => true])->click();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($tree->getByRole('img', ['name' => 'Has errors']))->toHaveCount(1);

        // Publish refuses, and selects the heading, whose field shows the error
        $tree->getByRole('button', ['name' => 'Entry settings'])->click();
        $browser->getByRole('button', ['name' => 'Publish'])->click();

        Expect::locator($browser->getByRole('alert'))->toHaveText("Couldn't publish: One error needs fixing");
        Expect::locator($tree->getByRole('button', ['name' => 'Heading', 'exact' => true]))->toHaveAttribute('aria-pressed', 'true');
        Expect::locator($browser->getByLabel('Text'))->toHaveAttribute('aria-describedby', sprintf('cms-field-%s-text-errors', $this->headingKey($browser)));
        Expect::locator($browser->getByRole('complementary')->getByText('This field is required.'))->toBeVisible();
        Expect::locator($browser->getByText('Never published', ['exact' => true]))->toBeVisible();

        // Filling it in clears the flag, and publish goes through
        $browser->getByLabel('Text')->fill('Hello');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($tree->getByRole('img', ['name' => 'Has errors']))->toHaveCount(0);
        Expect::locator($browser->getByRole('complementary')->getByText('This field is required.'))->toHaveCount(0);

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $browser = $this->visit('/en_US/about-us');
        Expect::locator($browser->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
    }

    /**
     * The key the builder gave the new heading, from the id of its Text field
     */
    private function headingKey(BrowserPage $browser): string
    {
        $id = $browser->getByLabel('Text')->getAttribute('id');
        self::assertIsString($id);
        self::assertSame(1, preg_match('/^cms-field-(.+)-text$/', $id, $matches));

        return $matches[1];
    }
}
