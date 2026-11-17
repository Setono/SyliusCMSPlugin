<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\BestsellersParams;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AnalyticsBestsellers;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AnalyticsBestsellersKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Bestsellers;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Orders;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A project with analytics data aliases BestsellersCalculatorInterface to its own calculator, and the command, the table
 * and the source stay the same. See "Bestsellers" in docs/spec/sources.md and docs/spec/extending.md
 */
final class ProjectBestsellersCalculatorTest extends KernelTestCase
{
    use Bestsellers;
    use Catalog;
    use Orders;

    protected static function getKernelClass(): string
    {
        return AnalyticsBestsellersKernel::class;
    }

    /**
     * @test
     */
    public function the_command_stores_the_scores_of_the_calculator_the_interface_is_aliased_to_and_the_source_lists_by_them(): void
    {
        $kernel = self::bootKernel();
        $shirt = self::createProduct('SHIRT', 'Shirt');
        self::createProduct('MUG', 'Mug');
        // The default calculator would have scored the shirt first
        self::createOrder([$shirt]);

        $tester = new CommandTester((new Application($kernel))->find('setono:cms:bestsellers:compute'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $scores = self::scoresOf('WEB');
        self::assertCount(AnalyticsBestsellers::SCORED, $scores);
        self::assertSame(['MUG' => 1000.5, 'SHIRT' => 1000.0, 'EXTRA_247' => 247.0], array_slice($scores, 0, 3));
        self::assertSame(0.5, $scores['42']);

        $registry = self::getContainer()->get(SourceRegistryInterface::class);
        self::assertInstanceOf(SourceRegistryInterface::class, $registry);
        $codes = [];
        foreach ($registry->get('bestsellers')->source->resolve(new BestsellersParams(), self::context())->getSlice(0, 8) as $product) {
            self::assertInstanceOf(ProductInterface::class, $product);
            $codes[] = $product->getCode();
        }
        self::assertSame(['MUG', 'SHIRT'], $codes);
    }

    private static function context(): RenderContext
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return new RenderContext($channel, 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
