<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Toggle;

final class WithToggle
{
    #[Toggle(label: 'app.on')]
    public bool $on = false;
}
