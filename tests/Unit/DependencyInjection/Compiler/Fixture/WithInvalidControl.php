<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

final class WithInvalidControl
{
    #[InvalidControl(label: 'app.hearts')]
    public ?int $hearts = null;
}
