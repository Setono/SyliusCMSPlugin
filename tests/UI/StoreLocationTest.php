<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * A project's own content type is edited in the builder like a page: the test application's store locations, which have
 * no block tree, so the tree pane is hidden, and a field of their own, the city, in the entry settings. See "Content
 * types" in docs/spec/extending.md
 */
final class StoreLocationTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_create_a_store_location_fill_in_its_city_publish_it_and_open_it_under_its_prefix(): void
    {
        $this->signInAsAdmin();

        $page = $this->visit('/admin/cms/entries/store_location/new');
        $page->getByLabel('Title')->fill('Aarhus');
        $page->getByLabel('Slug')->fill('aarhus');
        $page->getByRole('button', ['name' => 'Create'])->click();

        // The builder opens with the new store location, without a tree pane, as it has no block tree
        Expect::locator($page->getByRole('heading', ['level' => 1]))->toHaveText('Aarhus');
        Expect::locator($page->locator('#cms-builder')->getByRole('navigation'))->toHaveCount(0);
        Expect::locator($page->getByRole('button', ['name' => 'Add block', 'exact' => true]))->toHaveCount(0);

        // The city is required, so publish refuses it until it's filled in
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->locator('#cms-entry-field-city-errors'))->toBeVisible();

        $page->getByLabel('City')->fill('Aarhus C');
        $page->getByLabel('Directions')->fill('Turn left at the fountain');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');

        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $page = $this->visit('/en_US/stores/aarhus');

        self::assertSame(200, $this->getLastResponse()?->getStatusCode());
        Expect::locator($page->locator('.app-store h1'))->toHaveText('Aarhus');
        Expect::locator($page->locator('.app-store__city'))->toHaveText('Aarhus C');
        Expect::locator($page->locator('.app-store__directions'))->toHaveText('Turn left at the fountain');
    }
}
