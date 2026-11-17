<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's product property without parameters: the brand it's given, whatever the product
 */
#[AsBindableProperty(contextKey: 'product', code: 'brand', label: 'app.binding.brand', type: BindingType::Text)]
final class ProductBrand implements BindablePropertyInterface
{
    /** @var list<object> */
    public array $resolvedFor = [];

    public function __construct(private readonly ?string $brand = 'Acme')
    {
    }

    public function resolve(object $subject, object $params, RenderContext $context): ?string
    {
        $this->resolvedFor[] = $subject;

        return $this->brand;
    }
}
