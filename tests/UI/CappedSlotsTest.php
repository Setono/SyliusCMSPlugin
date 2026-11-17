<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;

/**
 * shop.top capped at 1, as a project caps a stock slot (SlotsKernel): only its first matching placement shows, and
 * editors put the slot's placements of every snippet in order in its Order… dialog. See "Capped slots" in
 * docs/spec/slots.md and "Placements" in docs/spec/admin.md
 */
final class CappedSlotsTest extends UITestCase
{
    use Placements;

    protected static function getKernelClass(): string
    {
        return SlotsKernel::class;
    }

    /**
     * @test
     */
    public function an_admin_user_drags_the_other_snippet_first_in_the_order_dialog_of_a_slot_capped_at_1_and_the_shop_shows_it(): void
    {
        $freeShipping = self::createSnippet('Free shipping', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Free shipping on orders over $50')], published: true);
        $sale = self::createSnippet('Winter sale', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Winter sale: 20% off')], published: true);
        self::place($freeShipping, 'shop.top');
        self::place($sale, 'shop.top');
        $this->signInAsAdmin();

        $shop = $this->visit('/en_US/');
        Expect::locator($shop->locator('.cms-slot--shop-top .cms-heading'))->toHaveText('Free shipping on orders over $50');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $sale->getUuid()));
        $row = $browser->getByRole('region', ['name' => 'Placements'])->locator('tbody tr');
        Expect::locator($row->locator('[data-position]'))->toHaveText('2 of 2 · capped at 1');
        Expect::locator($row->locator('[data-wins-for]'))->toHaveText('No one: a placement above it shows on every page to everyone');

        $row->getByRole('button', ['name' => 'Order…'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Order of "Every page: the top of the header"']);
        $placements = $dialog->getByRole('list', ['name' => 'The placements in "Every page: the top of the header", in order'])->getByRole('listitem');
        self::assertTexts(['Free shipping', 'Winter sale'], $placements->locator('[data-snippet-title]'));
        Expect::locator($placements->nth(1)->locator('[data-this-snippet]'))->toHaveText('This snippet');

        // Dragged above the other snippet's placement
        $placements->nth(1)->dragTo($placements->nth(0), ['targetPosition' => ['x' => 20, 'y' => 5]]);
        self::assertTexts(['Winter sale', 'Free shipping'], $placements->locator('[data-snippet-title]'));
        $dialog->getByRole('button', ['name' => 'Save order'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The order has been saved.');
        $row = $browser->getByRole('region', ['name' => 'Placements'])->locator('tbody tr');
        Expect::locator($row->locator('[data-position]'))->toHaveText('1 of 2 · capped at 1');
        Expect::locator($row->locator('[data-wins-for]'))->toHaveText('Everyone');

        $shop = $this->visit('/en_US/');
        Expect::locator($shop->locator('.cms-slot--shop-top .cms-heading'))->toHaveText('Winter sale: 20% off');
    }

    /**
     * The arrows move a placement without a mouse, and Cancel keeps the order as it was
     *
     * @test
     */
    public function an_admin_user_moves_a_placement_with_its_arrows_and_cancels_without_changing_the_order(): void
    {
        $freeShipping = self::createSnippet('Free shipping', published: true);
        $sale = self::createSnippet('Winter sale', published: true);
        self::place($freeShipping, 'shop.top');
        self::place($sale, 'shop.top');
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $freeShipping->getUuid()));
        $browser->getByRole('button', ['name' => 'Order…'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Order of "Every page: the top of the header"']);
        $placements = $dialog->getByRole('listitem');
        Expect::locator($placements->nth(0)->getByRole('button', ['name' => 'Move up: Free shipping']))->toBeDisabled();

        $placements->nth(0)->getByRole('button', ['name' => 'Move down: Free shipping'])->click();
        self::assertTexts(['Winter sale', 'Free shipping'], $placements->locator('[data-snippet-title]'));
        Expect::locator($placements->nth(1)->getByRole('button', ['name' => 'Move down: Free shipping']))->toBeDisabled();

        $dialog->getByRole('button', ['name' => 'Cancel'])->click();
        Expect::locator($dialog)->toBeHidden();
        $browser->getByRole('button', ['name' => 'Order…'])->click();
        self::assertTexts(['Free shipping', 'Winter sale'], $placements->locator('[data-snippet-title]'));
    }
}
