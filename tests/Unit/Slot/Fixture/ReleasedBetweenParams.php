<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot\Fixture;

use Setono\SyliusCMSPlugin\Field\Date;
use Setono\SyliusCMSPlugin\Validation\Constraint\EndsAfterStart;

#[EndsAfterStart(start: 'from', end: 'until')]
final class ReleasedBetweenParams
{
    #[Date(label: 'app.released_between.from')]
    public ?\DateTimeImmutable $from = null;

    #[Date(label: 'app.released_between.until')]
    public ?\DateTimeImmutable $until = null;
}
