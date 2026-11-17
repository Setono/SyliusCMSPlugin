<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A project's product source with parameters: the products it's given, or none when only new ones are asked for. It
 * records the parameters it resolved with and the adapters it returned
 */
#[AsSource(code: 'on_sale', label: 'app.source.on_sale', of: ProductInterface::class, params: OnSaleParams::class)]
final class OnSale implements SourceInterface
{
    /** @var list<OnSaleParams> */
    public array $resolvedWith = [];

    /** @var list<RecordingAdapter<ProductInterface>> */
    public array $adapters = [];

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

        return $this->adapters[] = new RecordingAdapter($params->onlyNew ? [] : $this->products);
    }
}
