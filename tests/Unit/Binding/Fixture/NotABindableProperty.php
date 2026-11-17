<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindingType;

#[AsBindableProperty(contextKey: 'product', code: 'weight', label: 'app.binding.weight', type: BindingType::Number)]
final class NotABindableProperty
{
}
