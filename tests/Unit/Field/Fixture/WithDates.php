<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Date;
use Setono\SyliusCMSPlugin\Field\DateTime;

final class WithDates
{
    #[Date(label: 'Day')]
    public ?\DateTimeImmutable $day = null;

    #[DateTime(label: 'At')]
    public ?\DateTimeImmutable $at = null;
}
