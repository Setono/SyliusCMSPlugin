<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

#[AsBindableProperty(contextKey: 'product', code: 'brand', label: 'app.binding.other_brand', type: BindingType::Text)]
final class SameBindingAsBrand implements BindablePropertyInterface
{
    public function resolve(object $subject, object $params, RenderContext $context): string|int|float|null
    {
        return null;
    }
}
