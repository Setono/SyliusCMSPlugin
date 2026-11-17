<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

final class WithFieldWithoutControl
{
    #[WithoutControl(label: 'app.hearts')]
    public ?int $hearts = null;
}
