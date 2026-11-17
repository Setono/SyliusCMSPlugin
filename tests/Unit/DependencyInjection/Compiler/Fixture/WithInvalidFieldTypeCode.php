<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

final class WithInvalidFieldTypeCode
{
    #[InvalidFieldTypeCode(label: 'app.stars')]
    public ?int $stars = null;
}
