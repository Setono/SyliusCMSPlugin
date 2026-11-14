<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

final class WithUntypedField
{
    #[Untyped(label: 'app.untyped')]
    public ?string $value = null;
}
