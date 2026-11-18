<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Toggle;

final class StockLevelParams
{
    #[Toggle(label: 'app.binding.stock_level.include_reserved')]
    public bool $includeReserved = false;

    #[Choice(label: 'app.binding.stock_level.warehouse', choices: ['main' => 'app.warehouse.main', 'outlet' => 'app.warehouse.outlet'])]
    public ?string $warehouse = null;
}
