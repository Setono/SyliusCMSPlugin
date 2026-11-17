<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

final class WithFieldWithoutFieldType
{
    #[WithoutFieldType(label: 'app.hearts')]
    public ?int $hearts = null;
}
