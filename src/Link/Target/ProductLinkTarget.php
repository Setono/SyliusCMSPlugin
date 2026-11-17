<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link\Target;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Link\ReferencingLinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A product, by its code. It leads to the product's page in the shop, named by the product's name, while the shopper
 * may see the product: enabled, in the channel and translated into the locale ("Visible queries" in
 * docs/spec/sources.md). See "Link targets" in docs/spec/extending.md
 */
#[AsLinkTarget(code: 'product', label: 'setono_sylius_cms.link_target.product')]
final readonly class ProductLinkTarget implements ReferencingLinkTargetInterface
{
    /**
     * @param ReferenceTypeLoaderInterface $products the products the shopper may see
     */
    public function __construct(
        private ReferenceTypeLoaderInterface $products,
        private UrlGeneratorInterface $urlGenerator,
        private SearchInterface $search,
    ) {
    }

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        $product = '' === $ref ? null : $this->products->load([$ref], $context)[$ref] ?? null;

        return null === $product ? null : $this->link($product, $ref, $context);
    }

    public function search(string $query, string $localeCode): iterable
    {
        foreach ($this->search->search($query, $localeCode) as $result) {
            yield $result->jsonSerialize();
        }
    }

    public function reference(string $ref): ?Reference
    {
        return '' === $ref ? null : new Reference(ReferenceType::PRODUCT, $ref, false);
    }

    public function link(object $record, string $ref, RenderContext $context): ?ResolvedLink
    {
        if (!$record instanceof ProductInterface || !$record->getTranslations()->containsKey($context->localeCode)) {
            return null;
        }

        $translation = $record->getTranslation($context->localeCode);
        $slug = $translation->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        return new ResolvedLink(
            'product',
            $ref,
            $this->urlGenerator->generate('sylius_shop_product_show', ['_locale' => $context->localeCode, 'slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
            (string) $translation->getName(),
        );
    }
}
