<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;

/**
 * The builder previews a snippet on the real product page that hosts its placement, with a sample product the editor
 * switches, and patches the edits into the outlined slot. See "Snippet preview" in docs/spec/builder.md and
 * "Previewing a snippet" in docs/spec/slots.md
 */
final class SnippetPreviewTest extends UITestCase
{
    use Catalog;
    use Placements;

    /**
     * @test
     */
    public function an_admin_user_previews_a_snippet_placed_on_product_pages_switches_the_sample_product_and_sees_an_edit_patched_into_the_slot(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap');
        $snippet = self::createSnippet('Mug care', [self::heading('0193a5be-0000-7000-8000-0000000000a1', 'Rinse after use')], context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        self::scope($placement, [['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG', 'CAP']]]]);
        $this->signInAsAdmin();

        // The preview is the page of a product in the placement's scope, with the draft in its outlined, labelled slot
        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $snippet->getUuid()));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();
        Expect::locator($preview->locator('#sylius-product-name'))->toHaveText('Mug');
        $slot = $preview->locator('[data-cms-slot-preview="product.after_price"]');
        Expect::locator($slot->locator('[data-cms-slot-label]'))->toHaveText('Product page: under the price');
        Expect::locator($slot->locator('.cms-heading'))->toHaveText('Rinse after use');

        // The top bar shows the snippet's context, its placement and its sample product
        Expect::locator($browser->getByTitle('The pages it can be placed on. Blocks and conditions that need a product or a taxon are offered only where it has one.'))->toHaveText('Product page');
        Expect::locator($browser->getByRole('combobox', ['name' => 'Placement']))->toHaveValue($placement->getUuid());
        Expect::locator($browser->getByRole('alert'))->toHaveCount(0);

        // The picker marks the products in the scope, and a product outside it shows a warning
        $browser->getByRole('button', ['name' => 'Sample: Mug'])->click();
        $samples = $browser->getByRole('dialog', ['name' => 'Sample']);
        Expect::locator($samples->getByRole('option', ['name' => 'Cap In the page scope']))->toBeVisible();
        Expect::locator($samples->getByRole('option', ['name' => 'T-shirt', 'exact' => true]))->toBeVisible();
        $samples->getByRole('option', ['name' => 'T-shirt', 'exact' => true])->click();

        Expect::locator($preview->locator('#sylius-product-name'))->toHaveText('T-shirt');
        Expect::locator($browser->getByRole('alert'))->toHaveText("T-shirt is outside the placement's page scope, so shoppers don't see the snippet on its page.");
        Expect::locator($browser->getByRole('button', ['name' => 'Sample: T-shirt']))->toBeVisible();

        // The snippet still shows there, and an edit is patched into the slot
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();
        $browser->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        $browser->getByRole('textbox', ['name' => 'Text'])->fill('Rinse and dry after use');
        Expect::locator($preview->locator('[data-cms-slot-preview="product.after_price"] .cms-slot--product-after-price .cms-heading'))->toHaveText('Rinse and dry after use');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
    }
}
