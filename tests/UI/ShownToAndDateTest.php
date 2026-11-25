<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Seeing the page in the builder's preview as a customer group would, at a chosen date and time.
 * See "Shown to and Date" in docs/spec/targeting.md and "Top bar" in docs/spec/builder.md
 */
final class ShownToAndDateTest extends UITestCase
{
    use Customers;

    /**
     * @test
     */
    public function it_lets_an_admin_user_pick_wholesale_in_shown_to_and_see_a_wholesale_only_block_appear(): void
    {
        self::createCustomerGroup('wholesale', 'Wholesale');
        $page = self::createPageWithBlocks('Prices', 'prices', [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Welcome'),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Wholesale prices', ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['wholesale']]]),
        ]);
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        $headings = $preview->locator('.cms-tree .cms-heading');
        $shownTo = $browser->getByRole('combobox', ['name' => 'Shown to']);

        // The page shows to everyone, so the preview starts as a guest sees it
        Expect::locator($headings)->toHaveCount(1);
        Expect::locator($headings)->toHaveText('Welcome');
        self::assertSame('@guest', $shownTo->inputValue());

        $shownTo->selectOption('Wholesale');

        Expect::locator($headings)->toHaveCount(2);
        Expect::locator($headings->nth(1))->toHaveText('Wholesale prices');
        self::assertSame('wholesale', $shownTo->inputValue());
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_pick_a_date_inside_a_blocks_window_and_see_it_appear(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $page = self::createPageWithBlocks('Campaign', 'campaign', [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Welcome'),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Christmas sale', ['kind' => 'date_window', 'negate' => false, 'params' => [
                'from' => $now->modify('+5 days')->format('Y-m-d\TH:i:s\Z'),
                'until' => $now->modify('+11 days')->format('Y-m-d\TH:i:s\Z'),
            ]]),
        ]);
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        $headings = $preview->locator('.cms-tree .cms-heading');
        $date = $browser->getByLabel('Date', ['exact' => true]);

        // Now, the sale hasn't started
        Expect::locator($headings)->toHaveCount(1);
        Expect::locator($browser->getByRole('button', ['name' => 'Now']))->toHaveAttribute('aria-pressed', 'true');

        // Noon in PHP's default time zone, which the Date is picked in, a week from now, is inside the window whatever that time zone is
        $date->fill($now->modify('+8 days')->format('Y-m-d') . 'T12:00');
        $date->press('Enter');

        Expect::locator($headings)->toHaveCount(2);
        Expect::locator($headings->nth(1))->toHaveText('Christmas sale');
        Expect::locator($browser->getByRole('button', ['name' => 'Now']))->toHaveAttribute('aria-pressed', 'false');

        $browser->getByRole('button', ['name' => 'Now'])->click();

        Expect::locator($headings)->toHaveCount(1);
        self::assertSame('', $date->inputValue());
    }

    /**
     * @param array<string, mixed> ...$conditions
     */
    private static function headingIf(string $key, string $text, array ...$conditions): StoredBlock
    {
        $heading = self::heading($key, $text);
        $heading->conditions = array_values($conditions);

        return $heading;
    }
}
