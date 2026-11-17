<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A project's product source without parameters
 */
#[AsSource(code: 'clearance', label: 'app.source.clearance', of: ProductInterface::class)]
final class Clearance implements SourceInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([]);
    }
}
