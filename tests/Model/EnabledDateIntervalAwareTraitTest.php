<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EnabledDateIntervalAwareInterface;
use Setono\SyliusCMSPlugin\Model\EnabledDateIntervalAwareTrait;

/**
 * @covers \Setono\SyliusCMSPlugin\Model\EnabledDateIntervalAwareTrait
 */
final class EnabledDateIntervalAwareTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_will_know_when_it_is_enabled(): void
    {
        $now = new \DateTimeImmutable();

        self::assertTrue($this->getObjectWithEnabledDates()->isEnabledOn($now));
        self::assertTrue($this->getObjectWithEnabledDates($now, $now)->isEnabledOn($now));
        self::assertTrue($this->getObjectWithEnabledDates($now->sub(new \DateInterval('PT1S')), $now)->isEnabledOn($now));
        self::assertTrue($this->getObjectWithEnabledDates($now, $now->add(new \DateInterval('PT1S')))->isEnabledOn($now));
        self::assertFalse($this->getObjectWithEnabledDates($now, $now->sub(new \DateInterval('PT1S')))->isEnabledOn($now));
        self::assertFalse($this->getObjectWithEnabledDates($now->add(new \DateInterval('PT1S')), $now)->isEnabledOn($now));
    }

    private function getObjectWithEnabledDates(
        \DateTimeInterface $enabledFrom = null,
        \DateTimeInterface $enabledUntil = null
    ): EnabledDateIntervalAwareInterface {
        return new class($enabledFrom, $enabledUntil) implements EnabledDateIntervalAwareInterface {
            use EnabledDateIntervalAwareTrait;

            public function __construct(
                \DateTimeInterface $enabledFrom = null,
                \DateTimeInterface $enabledUntil = null
            ) {
                $this->enabledFrom = $enabledFrom;
                $this->enabledUntil = $enabledUntil;
            }
        };
    }
}
