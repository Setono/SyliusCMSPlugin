<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * Editors make a snippet and show it in a slot of the shop's pages by placing it on the snippet's overview page. See
 * "Placements" in docs/spec/slots.md and docs/spec/admin.md
 */
final class SnippetsInSlotsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_create_a_snippet_publish_it_place_it_in_the_footer_and_see_it_in_the_shop_s_footer(): void
    {
        $this->signInAsAdmin();

        // The hub lists snippets, and creates one
        $browser = $this->visit('/admin/cms/entries/snippet');
        Expect::locator($browser->locator('[data-hub-menu] a.item.active'))->toContainText('Snippets');
        $browser = $this->visit('/admin/cms/entries/snippet/new');
        $browser->getByLabel('Title')->fill('Free shipping');
        $browser->getByRole('button', ['name' => 'Create'])->click();

        // The builder opens with it, and previews it on a plain shop page
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('Free shipping');
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Rich text', 'exact' => true])->click();
        $content = $browser->getByRole('textbox', ['name' => 'Content']);
        $content->click();
        $content->pressSequentially('Free shipping on orders over $50');
        Expect::locator($browser->frameLocator('iframe')->locator('.cms-slot > .cms-rich-text p'))->toHaveText('Free shipping on orders over $50');

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // Its overview page places it in the footer, which takes effect at once
        $browser->getByRole('link', ['name' => 'Back'])->click();
        $placements = $browser->getByRole('region', ['name' => 'Placements']);
        Expect::locator($placements)->toContainText('It shows in no slot yet.');
        $placements->getByLabel('Slot')->selectOption('Every page: the footer');
        $placements->getByRole('button', ['name' => 'Add placement'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The placement has been added.');
        $rows = $browser->getByRole('region', ['name' => 'Placements'])->locator('tbody tr');
        Expect::locator($rows)->toHaveCount(1);
        Expect::locator($rows->nth(0)->locator('[data-slot-label]'))->toHaveText('Every page: the footer');
        Expect::locator($rows->nth(0)->locator('[data-position]'))->toHaveText('1 of 1');

        // The shop shows it in the footer of every page
        $shop = $this->visit('/en_US/');
        Expect::locator($shop->locator('#footer .cms-slot.cms-slot--shop-footer > .cms-rich-text p'))->toHaveText('Free shipping on orders over $50');
    }
}
