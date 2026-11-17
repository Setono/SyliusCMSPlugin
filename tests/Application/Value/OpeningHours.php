<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Value;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * What a template gets for an opening hours field: the days the store is open, in week order, and its photo
 */
final readonly class OpeningHours
{
    /**
     * @param array<string, array{opens: string, closes: string}> $days by weekday, such as monday
     * @param MediaItemInterface|null $photo null when there's none, or it was deleted
     */
    public function __construct(
        public array $days,
        public ?MediaItemInterface $photo,
    ) {
    }
}
