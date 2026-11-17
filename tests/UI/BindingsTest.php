<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * An editor binds a snippet's Heading to the product's name with ⚡, and each product page shows its own product's
 * name. See "Bindings" in docs/spec/builder.md and docs/spec/slots.md
 */
final class BindingsTest extends UITestCase
{
    use Catalog;

    /**
     * Until a snippet previews with a sample object (#260), its preview has no product, so the binding shows its
     * fallback there
     *
     * @test
     */
    public function an_admin_user_binds_a_snippet_s_heading_to_the_product_s_name_publishes_it_and_sees_each_product_s_own_name(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $heading = new StoredBlock('0193a5be-0000-7000-8000-0000000000a1', 'heading', 1);
        $heading->setValue('text', 'en_US', 'Our product');
        $snippet = self::createSnippet('Product intro', [$heading], context: ['product']);
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $snippet->getUuid()));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();
        Expect::locator($preview->locator('.cms-heading'))->toHaveText('Our product');

        // ⚡ lists the product's text properties, the channel's and the customer's, and binds the heading
        $browser->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        $browser->getByRole('button', ['name' => 'Bind Text to a property'])->click();
        $properties = $browser->getByRole('dialog', ['name' => 'Bind Text to a property']);
        Expect::locator($properties->getByRole('button', ['name' => 'Customer first name']))->toBeVisible();
        Expect::locator($properties->getByRole('button', ['name' => 'Taxon name']))->toHaveCount(0);
        $properties->getByRole('button', ['name' => 'Product name', 'exact' => true])->click();

        // The field shows the property, and what it held is the fallback, which the preview shows without a product
        Expect::locator($browser->getByRole('group', ['name' => 'Shows Product name']))->toBeVisible();
        Expect::locator($browser->getByRole('textbox', ['name' => 'Fallback']))->toHaveValue('Our product');
        $browser->getByRole('textbox', ['name' => 'Fallback'])->fill('This product');
        Expect::locator($preview->locator('.cms-heading'))->toHaveText('This product');
        // Bound values can't be typed in place: the field isn't marked for it
        self::assertSame('This product', $preview->locator('.cms-heading')->innerHTML());

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $browser->getByRole('link', ['name' => 'Back'])->click();
        $placements = $browser->getByRole('region', ['name' => 'Placements']);
        $placements->getByLabel('Slot')->selectOption('Product page: under the price');
        $placements->getByRole('button', ['name' => 'Add placement'])->click();
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The placement has been added.');

        // Each product page shows its own product's name
        foreach (['t-shirt' => 'T-shirt', 'mug' => 'Mug'] as $slug => $name) {
            $shop = $this->visit('/en_US/products/' . $slug);
            Expect::locator($shop->locator('.cms-slot.cms-slot--product-after-price .cms-heading'))->toHaveText($name);
        }
    }
}
