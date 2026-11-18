<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;

/**
 * Editors make a snippet and show it in a slot of the shop's pages by placing it on the snippet's overview page. See
 * "Placements" in docs/spec/slots.md and docs/spec/admin.md
 */
final class SnippetsInSlotsTest extends UITestCase
{
    use Placements;

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

    /**
     * A slot that isn't capped is ordered in the same Order… dialog as a capped one, without the cap's warnings
     *
     * @test
     */
    public function it_lets_an_admin_user_put_a_snippet_first_in_the_footer_with_its_order_dialog_and_see_the_new_order_in_the_shop(): void
    {
        $hours = self::createSnippet('Opening hours', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Open every day')], published: true);
        $shipping = self::createSnippet('Free shipping', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Free shipping on orders over $50')], published: true);
        self::place($hours, 'shop.footer');
        self::place($shipping, 'shop.footer');
        $this->signInAsAdmin();

        $shop = $this->visit('/en_US/');
        self::assertTexts(['Open every day', 'Free shipping on orders over $50'], $shop->locator('.cms-slot--shop-footer .cms-heading'));

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $shipping->getUuid()));
        $row = $browser->getByRole('region', ['name' => 'Placements'])->locator('tbody tr');
        Expect::locator($row->locator('[data-position]'))->toHaveText('2 of 2');
        Expect::locator($row->getByRole('button', ['name' => 'Move up']))->toHaveCount(0);

        $row->getByRole('button', ['name' => 'Order…'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Order of "Every page: the footer"']);
        Expect::locator($dialog->locator('[data-slot-warning]'))->toHaveCount(0);
        $placements = $dialog->getByRole('listitem');
        $placements->nth(1)->getByRole('button', ['name' => 'Move up: Free shipping'])->click();
        self::assertTexts(['Free shipping', 'Opening hours'], $placements->locator('[data-snippet-title]'));
        $dialog->getByRole('button', ['name' => 'Save order'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The order has been saved.');
        Expect::locator($browser->getByRole('region', ['name' => 'Placements'])->locator('tbody tr [data-position]'))->toHaveText('1 of 2');

        $shop = $this->visit('/en_US/');
        self::assertTexts(['Free shipping on orders over $50', 'Open every day'], $shop->locator('.cms-slot--shop-footer .cms-heading'));
    }
}
