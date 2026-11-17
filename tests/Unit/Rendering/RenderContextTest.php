<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Product;

final class RenderContextTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_the_same_context_in_another_preview_mode(): void
    {
        $product = new Product();
        $customer = new Customer();
        $now = new \DateTimeImmutable('2026-12-01 10:00:00');
        $context = Rendering::context('da_DK', previewMode: RenderContext::PREVIEW_MODE_BUILDER, hostPageContext: ['product' => $product], now: $now, customerGroup: 'wholesale', customer: $customer, currencyCode: 'DKK');

        $asShoppersSeeIt = $context->withPreviewMode(null);

        self::assertNull($asShoppersSeeIt->previewMode);
        self::assertSame($context->channel, $asShoppersSeeIt->channel);
        self::assertSame('da_DK', $asShoppersSeeIt->localeCode);
        self::assertSame('DKK', $asShoppersSeeIt->currencyCode);
        self::assertSame($customer, $asShoppersSeeIt->customer);
        self::assertSame('wholesale', $asShoppersSeeIt->customerGroup);
        self::assertSame($now, $asShoppersSeeIt->now);
        self::assertSame(['product' => $product], $asShoppersSeeIt->getHostPageContext());
        self::assertSame(RenderContext::PREVIEW_MODE_BUILDER, $context->previewMode, 'It leaves the context it came from as it is');
    }
}
