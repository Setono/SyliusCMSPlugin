<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Bestsellers;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Scores how well each product sells in a channel. setono:cms:bestsellers:compute calls it per channel and stores the
 * scores, and the Bestsellers source lists the products a shopper may see by them. The default counts completed orders over
 * bestsellers.window. A project with analytics data aliases this interface to its own calculator, and the command, the
 * table and the source stay the same. See "Bestsellers" in docs/spec/extending.md and docs/spec/sources.md
 */
interface BestsellersCalculatorInterface
{
    /**
     * @return iterable<string, int|float> product code => score; higher sells better. A product left out isn't listed
     */
    public function calculate(ChannelInterface $channel): iterable;
}
