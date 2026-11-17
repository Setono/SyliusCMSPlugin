<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

#[AsBindableProperty(contextKey: 'product', code: 'colour', label: 'app.binding.colour', type: BindingType::Text, params: 'App\Binding\ColourParams')]
final class MissingParams implements BindablePropertyInterface
{
    public function resolve(object $subject, object $params, RenderContext $context): string|int|float|null
    {
        return null;
    }
}
