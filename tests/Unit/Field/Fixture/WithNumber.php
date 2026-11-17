<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Number;

final class WithNumber
{
    #[Number(label: 'app.interval', min: 2, max: 30, integer: true)]
    public int $interval = 5;
}
