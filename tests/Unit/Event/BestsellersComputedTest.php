<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Event\BestsellersComputed;

final class BestsellersComputedTest extends TestCase
{
    /**
     * @test
     */
    public function it_changes_shopper_output_that_cannot_be_narrowed_to_records_in_the_channels_it_computed(): void
    {
        $event = new BestsellersComputed(['WEB', 'OUTLET']);

        self::assertNull($event->getAffectedUuids());
        self::assertSame(['WEB', 'OUTLET'], $event->channelCodes);
    }
}
