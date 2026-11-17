<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Code;

final class WithCode
{
    #[Code(label: 'app.embed')]
    public ?string $embed = null;
}
