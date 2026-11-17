<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's product property with parameters: a number, more with reserved stock included. It records the parameters
 * it resolved with
 */
#[AsBindableProperty(contextKey: 'product', code: 'stock_level', label: 'app.binding.stock_level', type: BindingType::Number, params: StockLevelParams::class)]
final class StockLevel implements BindablePropertyInterface
{
    /** @var list<object> */
    public array $resolvedWith = [];

    public function resolve(object $subject, object $params, RenderContext $context): int
    {
        $this->resolvedWith[] = $params;

        return $params instanceof StockLevelParams && $params->includeReserved ? 12 : 7;
    }
}
