<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Orders;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A Products block filled by Bestsellers: once setono:cms:bestsellers:compute has scored the paid orders, the editor
 * picks "Bestsellers" in "Filled by", and shoppers see the best seller first. See "Bestsellers" in docs/spec/sources.md
 */
final class BestsellersTest extends UITestCase
{
    use Catalog;
    use Orders;

    /**
     * @test
     */
    public function it_lets_an_admin_user_fill_a_products_block_with_the_bestsellers_and_shoppers_see_the_best_seller_first(): void
    {
        $shirt = self::createProduct('SHIRT', 'Shirt');
        $mug = self::createProduct('MUG', 'Mug');
        $cap = self::createProduct('CAP', 'Cap');
        self::createProduct('HAT', 'Hat');
        self::createOrder([$mug, $shirt]);
        self::createOrder([$mug, $cap]);
        self::createOrder([$mug]);
        self::createOrder([$cap]);
        // Not paid, so it doesn't count
        self::createOrder([$shirt], OrderPaymentStates::STATE_AWAITING_PAYMENT);
        self::computeBestsellers();
        $this->signInAsAdmin();
        $entry = self::createPage('Bestsellers', 'bestsellers');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Products', 'exact' => true])->click();

        $fields = $page->getByRole('complementary');
        $fields->getByRole('combobox', ['name' => 'Filled by'])->selectOption('Bestsellers');

        // Its taxon is optional, so the channel's bestsellers in every taxon show at once: the three products sold
        Expect::locator($fields->getByRole('button', ['name' => 'Taxon', 'exact' => true]))->toBeVisible();
        Expect::locator($fields->getByText('3 items now'))->toBeVisible();
        self::assertTexts(['Mug', 'Cap', 'Shirt'], $page->frameLocator('iframe')->locator('.cms-tree > .cms-products .sylius-product-name'));

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/bestsellers');
        self::assertTexts(['Mug', 'Cap', 'Shirt'], $shop->locator('.cms-tree > .cms-products > .cms-products__item .sylius-product-name'));
    }

    /**
     * Runs setono:cms:bestsellers:compute in the test's kernel, inside its database transaction, as cron would
     */
    private static function computeBestsellers(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $tester = new CommandTester((new Application($kernel))->find('setono:cms:bestsellers:compute'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
