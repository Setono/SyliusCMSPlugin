<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Bestsellers\BestsellersCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * A project's bestsellers calculator, as one with analytics data writes it: it scores by views rather than orders, here
 * the same 250 products in every channel, Mug first, then Shirt, the 247 Extra products and the product 42. Only
 * AnalyticsBestsellersKernel aliases the calculator interface to it
 */
final class AnalyticsBestsellers implements BestsellersCalculatorInterface
{
    public const SCORED = 250;

    public function calculate(ChannelInterface $channel): iterable
    {
        yield 'MUG' => 1000.5;
        yield 'SHIRT' => 1000;

        for ($views = self::SCORED - 3; $views > 0; --$views) {
            yield sprintf('EXTRA_%03d', $views) => $views;
        }

        // A numeric code, which PHP would turn into an integer key
        yield '42' => 0.5;
    }
}
