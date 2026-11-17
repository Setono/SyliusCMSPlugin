<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

#[AsSource(code: 'On-Sale', label: 'app.source.invalid', of: ProductInterface::class)]
final class InvalidCode implements SourceInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([]);
    }
}
