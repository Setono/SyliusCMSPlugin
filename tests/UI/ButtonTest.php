<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The Button block: an editor links it to a product found with the search picker, leaves its label empty and picks a
 * style, and shoppers see a button named by the product that leads to the product's page. See "Button" in
 * docs/spec/blocks.md and "Links" in docs/spec/content-model.md
 */
final class ButtonTest extends UITestCase
{
    use Catalog;

    /**
     * @test
     */
    public function it_lets_an_admin_user_link_a_button_to_a_product_that_shoppers_see_named_by_the_product(): void
    {
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createProduct('MUG', 'Mug');
        $this->signInAsAdmin();
        $entry = self::createPage('Shop now', 'shop-now');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Button', 'exact' => true])->click();

        // The link goes to a product, found by searching for it
        $page->getByRole('combobox', ['name' => 'Link'])->selectOption('product');
        $page->getByRole('button', ['name' => 'Choose…', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Search: Product'])->fill('shirt');
        $page->getByRole('option', ['name' => 'Red T-shirt'])->click();
        Expect::locator($page->getByText('Red T-shirt', ['exact' => true]))->toBeVisible();

        // The label stays empty, so the button shows the product's name
        Expect::locator($page->getByRole('textbox', ['name' => 'Label', 'exact' => true]))->toHaveValue('');
        Expect::locator($page->getByRole('textbox', ['name' => 'Label', 'exact' => true]))->toHaveAttribute('placeholder', 'Red T-shirt');
        // A Button holds nothing but its link, so its row has a box for the label too
        Expect::locator($page->getByRole('navigation', ['name' => 'Blocks'])->getByRole('textbox', ['name' => 'Link label']))->toHaveAttribute('placeholder', 'Red T-shirt');

        $page->getByRole('combobox', ['name' => 'Style'])->selectOption('secondary');

        // The preview shows the button before it's published
        Expect::locator($page->frameLocator('iframe')->locator('.cms-tree > a.cms-button.cms-button--secondary'))->toHaveText('Red T-shirt');

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/shop-now');
        $button = $shop->locator('.cms-tree > a.cms-button.cms-button--secondary');
        Expect::locator($button)->toHaveText('Red T-shirt');

        $button->click();

        Expect::locator($shop->locator('h1'))->toContainText('Red T-shirt');
        self::assertStringEndsWith('/en_US/products/red-t-shirt', $shop->url());
    }

    /**
     * @test
     */
    public function it_flags_a_button_that_links_to_an_unpublished_entry_in_the_tree_and_the_preview(): void
    {
        $this->signInAsAdmin();
        $team = self::createPage('Our team', 'team');
        $entry = self::createPageWithBlocks('About us', 'about', [
            new StoredBlock('0193a5be-0000-7000-8000-0000000000b1', 'button', 1, fields: ['link' => ['target' => 'entry', 'ref' => $team->getUuid(), 'anchor' => null, 'label' => ['en_US' => 'Meet the team']]]),
        ]);

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));

        $preview = $page->frameLocator('iframe[title="Preview"]');
        // The bridge has drawn its controls into the page once the builder has set it up
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();

        $notice = $preview->locator('.cms-tree [data-cms-unresolved-link="link"]');
        Expect::locator($notice)->toHaveText("Link isn't shown here: what it links to isn't visible in this channel and locale, or was deleted.");
        $row = $page->getByRole('navigation', ['name' => 'Blocks'])->getByRole('img', ['name' => "A link here isn't shown in the preview's channel and locale"]);
        Expect::locator($row)->toBeVisible();

        // Once the entry is published, the link resolves: the preview shows the button, and the flag goes
        self::publishEntry(self::reloadPage($team));
        $page->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Button', 'exact' => true])->click();
        $page->getByRole('textbox', ['name' => 'Label', 'exact' => true])->fill('Meet our team');

        Expect::locator($preview->locator('.cms-tree a.cms-button'))->toHaveText('Meet our team');
        Expect::locator($row)->toHaveCount(0);
    }
}
