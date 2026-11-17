<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Bestsellers\BestsellersCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * A project's bestsellers calculator, as one with analytics data writes it: it scores by views rather than orders, here
 * the same 250 products in every channel, Mug first, then Shirt, then the 248 Extra products. Only
 * AnalyticsBestsellersKernel aliases the calculator interface to it
 */
final class AnalyticsBestsellers implements BestsellersCalculatorInterface
{
    public const SCORED = 250;

    public function calculate(ChannelInterface $channel): \Generator
    {
        yield 'MUG' => 1000.5;
        yield 'SHIRT' => 1000;

        for ($views = self::SCORED - 2; $views > 0; --$views) {
            yield sprintf('EXTRA_%03d', $views) => $views;
        }
    }
}
