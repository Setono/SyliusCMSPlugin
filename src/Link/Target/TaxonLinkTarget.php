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
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A taxon, by its code. It leads to the path of the taxon's product listing in the shop, named by the taxon's name,
 * while it's enabled ("Visible queries" in docs/spec/sources.md) and has a translation in the locale, which its URL
 * needs. See "Link targets" in docs/spec/extending.md
 */
#[AsLinkTarget(code: 'taxon', label: 'setono_sylius_cms.link_target.taxon')]
final readonly class TaxonLinkTarget implements ReferencingLinkTargetInterface
{
    /**
     * @param ReferenceTypeLoaderInterface $taxons the taxons the shopper may see
     */
    public function __construct(
        private ReferenceTypeLoaderInterface $taxons,
        private UrlGeneratorInterface $urlGenerator,
        private SearchInterface $search,
    ) {
    }

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        $taxon = '' === $ref ? null : $this->taxons->load([$ref], $context)[$ref] ?? null;

        return null === $taxon ? null : $this->link($taxon, $ref, $context);
    }

    public function search(string $query, string $localeCode): iterable
    {
        foreach ($this->search->search($query, $localeCode) as $result) {
            yield $result->jsonSerialize();
        }
    }

    public function reference(string $ref): ?Reference
    {
        return '' === $ref ? null : new Reference(ReferenceType::TAXON, $ref, false);
    }

    public function link(object $record, string $ref, RenderContext $context): ?ResolvedLink
    {
        if (!$record instanceof TaxonInterface || !$record->getTranslations()->containsKey($context->localeCode)) {
            return null;
        }

        $translation = $record->getTranslation($context->localeCode);
        $slug = $translation->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        return new ResolvedLink(
            'taxon',
            $ref,
            $this->urlGenerator->generate('sylius_shop_product_index', ['_locale' => $context->localeCode, 'slug' => $slug]),
            (string) $translation->getName(),
        );
    }
}
