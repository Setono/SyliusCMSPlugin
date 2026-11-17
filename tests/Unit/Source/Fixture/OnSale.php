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
 * A project's product source with parameters: the products it's given in its taxon, or none when only new ones are
 * asked for
 */
#[AsSource(code: 'on_sale', label: 'app.source.on_sale', of: ProductInterface::class, params: OnSaleParams::class)]
final class OnSale implements SourceInterface
{
    /** @var list<OnSaleParams> the parameters it resolved with */
    public array $resolvedWith = [];

    /**
     * @param list<ProductInterface> $products
     */
    public function __construct(private readonly array $products = [])
    {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof OnSaleParams) {
            throw new \InvalidArgumentException(sprintf('On sale takes %s, got %s', OnSaleParams::class, $params::class));
        }

        $this->resolvedWith[] = $params;

        return new ArrayAdapter($params->onlyNew ? [] : $this->products);
    }
}
