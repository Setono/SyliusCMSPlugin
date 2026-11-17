<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Event\BestsellersComputed;
use Setono\SyliusCMSPlugin\Tests\Support\Bestsellers;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Orders;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * setono:cms:bestsellers:compute runs the bestsellers calculator for every channel, or one, replaces each channel's
 * scores, and then dispatches BestsellersComputed. The default calculator counts each product's paid orders over the
 * last 30 days. See "Bestsellers" in docs/spec/sources.md and "Console commands" in docs/spec/extending.md
 */
final class ComputeBestsellersCommandTest extends KernelTestCase
{
    use Bestsellers;
    use Catalog;
    use Entries;
    use Orders;

    /** @var list<BestsellersComputed> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        self::bootKernel();
        self::createChannel('OUTLET');

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(BestsellersComputed::class, function (BestsellersComputed $event): void {
            $this->dispatched[] = $event;
        });
    }

    /**
     * @test
     */
    public function it_scores_each_product_by_how_many_paid_orders_inside_the_last_30_days_contained_it_in_each_channel(): void
    {
        $shirt = self::createProduct('SHIRT', 'Shirt', channels: ['WEB', 'OUTLET']);
        $mug = self::createProduct('MUG', 'Mug', channels: ['WEB', 'OUTLET']);
        $cap = self::createProduct('CAP', 'Cap');
        self::createOrder([$shirt, $mug]);
        self::createOrder([$shirt], checkoutCompletedAt: new \DateTime('-29 days'));
        // An order counts once, however many of its items are the product
        self::createOrder([$shirt, $shirt]);
        // Orders that don't count: not paid, or completed before the window
        self::createOrder([$cap], OrderPaymentStates::STATE_AWAITING_PAYMENT);
        self::createOrder([$cap], OrderPaymentStates::STATE_REFUNDED);
        self::createOrder([$cap], checkoutCompletedAt: new \DateTime('-31 days'));
        self::createOrder([$mug], channelCode: 'OUTLET');

        $tester = self::compute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['SHIRT' => 3.0, 'MUG' => 1.0], self::scoresOf('WEB'));
        self::assertSame(['MUG' => 1.0], self::scoresOf('OUTLET'));
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('WEB: scored 2 products', $display);
        self::assertStringContainsString('OUTLET: scored 1 product', $display);
    }

    /**
     * @test
     */
    public function it_replaces_the_scores_of_the_channel_it_is_given_and_leaves_the_other_channels_alone(): void
    {
        $shirt = self::createProduct('SHIRT', 'Shirt');
        self::createOrder([$shirt]);
        self::scoreProducts(['SHIRT' => 10, 'GONE' => 5]);
        self::scoreProducts(['MUG' => 3], 'OUTLET');

        $tester = self::compute(['--channel' => 'WEB']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['SHIRT' => 1.0], self::scoresOf('WEB'));
        self::assertSame(['MUG' => 3.0], self::scoresOf('OUTLET'));
    }

    /**
     * @test
     */
    public function it_leaves_a_channel_without_paid_orders_without_scores(): void
    {
        self::scoreProducts(['SHIRT' => 10]);

        self::compute();

        self::assertSame([], self::scoresOf('WEB'));
    }

    /**
     * @test
     */
    public function it_dispatches_bestsellers_computed_with_the_channels_it_computed_once_it_is_done(): void
    {
        self::compute();
        self::compute(['--channel' => 'OUTLET']);

        self::assertCount(2, $this->dispatched);
        self::assertSame(['WEB', 'OUTLET'], $this->dispatched[0]->channelCodes);
        self::assertNull($this->dispatched[0]->getAffectedUuids());
        self::assertSame(['OUTLET'], $this->dispatched[1]->channelCodes);
    }

    /**
     * @test
     */
    public function it_fails_on_a_channel_the_store_does_not_have_and_changes_nothing(): void
    {
        self::scoreProducts(['SHIRT' => 10]);

        $tester = self::compute(['--channel' => 'NOPE']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('The store has no channel with the code "NOPE".', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertSame(['SHIRT' => 10.0], self::scoresOf('WEB'));
        self::assertSame([], $this->dispatched);
    }

    /**
     * @param array<string, string> $input
     */
    private static function compute(array $input = []): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $tester = new CommandTester((new Application($kernel))->find('setono:cms:bestsellers:compute'));
        $tester->execute($input);

        return $tester;
    }
}
