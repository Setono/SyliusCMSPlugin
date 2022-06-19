<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Checker\Eligibility\Page\EnabledDateEligibilityChecker;
use Setono\SyliusCMSPlugin\Model\PageInterface;

/**
 * @covers \Setono\SyliusCMSPlugin\Checker\Eligibility\Page\EnabledDateEligibilityChecker
 */
final class EnabledDateEligibilityCheckerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_eligible_when_current_channel_is_part_of_pages_channels(): void
    {
        $page = $this->prophesize(PageInterface::class);
        $page->isEnabledOn(Argument::type(\DateTimeImmutable::class))->willReturn(true);

        $checker = new EnabledDateEligibilityChecker();
        self::assertTrue($checker->isEligible($page->reveal()));
    }
}
