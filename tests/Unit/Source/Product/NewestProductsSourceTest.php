<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Product;

use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\NewestProductsSource;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\RecordsQueries;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ProductInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

/**
 * "Newest": the visible products, newest first, then by ID. See "Product sources" in docs/spec/sources.md
 */
final class NewestProductsSourceTest extends TestCase
{
    use ProphecyTrait;
    use RecordsQueries;

    /**
     * @test
     */
    public function it_is_the_shipped_newest_source_of_products_without_parameters(): void
    {
        $attribute = AttributeReader::source(NewestProductsSource::class);

        self::assertNotNull($attribute);
        self::assertSame('newest_products', $attribute->code);
        self::assertSame('setono_sylius_cms.source.newest_products', $attribute->label);
        self::assertSame(ProductInterface::class, $attribute->of);
        self::assertSame([], $attribute->needs);
        self::assertNull($attribute->params);
    }

    /**
     * @test
     */
    public function it_pages_the_visible_products_newest_first_then_by_id(): void
    {
        $adapter = (new NewestProductsSource($this->visibleProducts()))->resolve(new NoParams(), self::context());

        self::assertInstanceOf(QueryAdapter::class, $adapter);
        self::assertStringContainsString('product.enabled = :cms_visible_enabled', $this->dql());
        self::assertStringEndsWith('ORDER BY product.createdAt DESC, product.id DESC', $this->dql());
    }

    private static function context(): RenderContext
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return Rendering::context(channel: $channel);
    }
}
