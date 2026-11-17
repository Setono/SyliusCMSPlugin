<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Attribute\AsContextResolver;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The taxon of Sylius's taxon page, found from its URL as the page finds it: enabled, by its slug in the locale. The grid
 * events on the taxon page don't carry the taxon, so taxon.before_grid and taxon.after_grid resolve it.
 * See "Context resolvers" in docs/spec/slots.md
 */
#[AsContextResolver('taxon')]
final readonly class TaxonContextResolver implements ContextResolverInterface
{
    /** Sylius's taxon page, which lists the taxon's products */
    public const ROUTE = 'sylius_shop_product_index';

    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxons
     */
    public function __construct(
        private TaxonRepositoryInterface $taxons,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function resolve(Request $request): ?object
    {
        $slug = $request->attributes->get('slug');
        if (self::ROUTE !== $request->attributes->get('_route') || !is_string($slug)) {
            return null;
        }

        return $this->taxons->findOneBySlug($slug, $this->localeContext->getLocaleCode());
    }
}
