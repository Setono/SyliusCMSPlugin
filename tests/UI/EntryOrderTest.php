<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;

/**
 * Editors drag the entries of a content type ordered by position into order in its list, and the order takes effect at
 * once. The test application's announcements are ordered by position. See "Entry lists" in docs/spec/admin.md
 */
final class EntryOrderTest extends UITestCase
{
    use StoreLocations;

    /**
     * @test
     */
    public function it_lets_an_admin_user_drag_two_announcements_into_a_new_order_which_the_list_keeps(): void
    {
        self::createAnnouncement('Free shipping');
        self::createAnnouncement('Summer sale');
        $this->signInAsAdmin();

        $page = $this->visit('/admin/cms/entries/announcement');
        $titles = $page->locator('[data-column="title"] strong');
        self::assertTexts(['Free shipping', 'Summer sale'], $titles);

        $rows = $page->locator('tbody tr');
        $rows->nth(1)->dragTo($rows->nth(0), ['targetPosition' => ['x' => 20, 'y' => 5]]);

        Expect::locator($page->getByRole('status'))->toHaveText('The order is saved.');
        self::assertTexts(['Summer sale', 'Free shipping'], $titles);

        $page = $this->visit('/admin/cms/entries/announcement');
        self::assertTexts(['Summer sale', 'Free shipping'], $page->locator('[data-column="title"] strong'));

        // The arrows move a row too
        $page->getByRole('button', ['name' => 'Move up: Free shipping'])->click();

        Expect::locator($page->getByRole('status'))->toHaveText('The order is saved.');
        $page = $this->visit('/admin/cms/entries/announcement');
        self::assertTexts(['Free shipping', 'Summer sale'], $page->locator('[data-column="title"] strong'));
    }
}
