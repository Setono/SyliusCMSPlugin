<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's product property of type url: the URL it's given, whatever the product
 */
#[AsBindableProperty(contextKey: 'product', code: 'page', label: 'app.binding.product_page', type: BindingType::Url)]
final class ProductPage implements BindablePropertyInterface
{
    public function __construct(private readonly ?string $url = '/en_US/products/t-shirt')
    {
    }

    public function resolve(object $subject, object $params, RenderContext $context): string|int|float|null
    {
        return $this->url;
    }
}
