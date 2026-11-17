<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A project's product source that needs the host page's product and taxon
 */
#[AsSource(code: 'neighbours', label: 'app.source.neighbours', of: ProductInterface::class, needs: ['product', 'taxon'])]
final class Neighbours implements SourceInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([]);
    }
}
