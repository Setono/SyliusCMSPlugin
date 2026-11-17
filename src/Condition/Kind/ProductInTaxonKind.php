<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

/**
 * Matches when the page's product is in one of the taxons, through any of its taxons, not only its main one. With
 * includeChildren, a product in a taxon below one of them matches too. A deleted taxon, or one the shopper may not see,
 * matches nothing. See "Condition kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.product_in_taxon', needs: ['product'], reads: [Reads::HostPage], params: ProductInTaxonParams::class)]
final class ProductInTaxonKind implements ConditionKindInterface
{
    public const CODE = 'product_in_taxon';

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof ProductInTaxonParams) {
            throw new \InvalidArgumentException(sprintf('The product in taxon condition takes %s, got %s', ProductInTaxonParams::class, $params::class));
        }

        $product = $context->get('product');
        if (!$product instanceof ProductInterface || null === $params->taxons) {
            return false;
        }

        $picked = [];
        foreach ($params->taxons as $taxon) {
            $picked[(string) $taxon->getCode()] = true;
        }

        foreach ($product->getTaxons() as $taxon) {
            if (self::isIn($taxon, $picked, $params->includeChildren)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the taxon is one of the picked taxons, or, with children included, below one of them
     *
     * @param array<string, true> $picked by code
     */
    private static function isIn(TaxonInterface $taxon, array $picked, bool $includeChildren): bool
    {
        for ($current = $taxon; null !== $current; $current = $includeChildren ? $current->getParent() : null) {
            if (isset($picked[(string) $current->getCode()])) {
                return true;
            }
        }

        return false;
    }
}
