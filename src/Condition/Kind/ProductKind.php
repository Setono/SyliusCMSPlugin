<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Matches when the page's product is one of the products. A deleted product, or one the shopper may not see, matches
 * nothing. It works in page scopes, and in visibility conditions where the product is in the context. See "Condition
 * kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.product', needs: ['product'], reads: [Reads::HostPage], params: ProductParams::class)]
final class ProductKind implements ConditionKindInterface
{
    public const CODE = 'product';

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof ProductParams) {
            throw new \InvalidArgumentException(sprintf('The product condition takes %s, got %s', ProductParams::class, $params::class));
        }

        $product = $context->get('product');
        if (!$product instanceof ProductInterface || null === $params->products) {
            return false;
        }

        foreach ($params->products as $picked) {
            if ($picked->getCode() === $product->getCode()) {
                return true;
            }
        }

        return false;
    }
}
