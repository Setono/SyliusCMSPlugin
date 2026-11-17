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
     * The snippet's preview shows it with a sample product, the first by name while it has no placement, so the binding
     * shows that product's name there. A customer binding has no customer in the preview, so it shows its fallback
     *
     * @test
     */
    public function it_lets_an_admin_user_bind_a_snippet_s_heading_to_the_product_s_name_publish_it_and_see_each_product_s_own_name(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $heading = new StoredBlock('0193a5be-0000-7000-8000-0000000000a1', 'heading', 1);
        $heading->setValue('text', 'en_US', 'Our product');
        $greeting = new StoredBlock('0193a5be-0000-7000-8000-0000000000a2', 'heading', 1, fields: ['text' => ['$bind' => 'customer.first_name', '$params' => [], '$fallback' => ['en_US' => 'Hi there']]]);
        $snippet = self::createSnippet('Product intro', [$heading, $greeting], context: ['product']);
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $snippet->getUuid()));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();
        Expect::locator($preview->locator('.cms-heading')->first())->toHaveText('Our product');
        Expect::locator($preview->locator('.cms-heading')->nth(1))->toHaveText('Hi there');
        Expect::locator($browser->getByRole('button', ['name' => 'Sample: Mug']))->toBeVisible();

        // ⚡ lists the product's text properties, the channel's and the customer's, and binds the heading
        $browser->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        $browser->getByRole('button', ['name' => 'Bind to a property'])->click();
        $properties = $browser->getByRole('dialog', ['name' => 'Bind to a property']);
        Expect::locator($properties->getByRole('button', ['name' => 'Customer first name']))->toBeVisible();
        Expect::locator($properties->getByRole('button', ['name' => 'Taxon name']))->toHaveCount(0);
        $properties->getByRole('button', ['name' => 'Product name', 'exact' => true])->click();

        // The field shows the property, and what it held is the fallback. The preview shows the sample product's name
        Expect::locator($browser->getByRole('group', ['name' => 'Shows Product name']))->toBeVisible();
        Expect::locator($browser->getByRole('textbox', ['name' => 'Fallback']))->toHaveValue('Our product');
        $browser->getByRole('textbox', ['name' => 'Fallback'])->fill('This product');
        Expect::locator($preview->locator('.cms-heading')->first())->toHaveText('Mug');
        // Bound values can't be typed in place: the field isn't marked for it
        self::assertSame('Mug', $preview->locator('.cms-heading')->first()->innerHTML());

        // Another sample product shows its own name
        $browser->getByRole('button', ['name' => 'Sample: Mug'])->click();
        $browser->getByRole('dialog', ['name' => 'Sample'])->getByRole('option', ['name' => 'T-shirt'])->click();
        Expect::locator($preview->locator('.cms-heading')->first())->toHaveText('T-shirt');
        Expect::locator($preview->locator('.cms-heading')->nth(1))->toHaveText('Hi there');

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
            Expect::locator($shop->locator('.cms-slot.cms-slot--product-after-price .cms-heading')->first())->toHaveText($name);
        }
    }
}
