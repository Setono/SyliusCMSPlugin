<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The pages of the stock context keys: a product's page, a taxon's product listing and an entry's own URL, for a slot
 * that provides the sample object's key, and for a layout's slot only when the entry's layout renders it. A slot that
 * provides nothing shows on the homepage when it's on every page of the shop's layout, such as shop.footer, or on
 * Sylius's homepage, such as store_homepage, unless the channel's homepage is a CMS page, which replaces Sylius's
 */
final readonly class HostPageUrlGenerator implements HostPageUrlGeneratorInterface
{
    private const LAYOUT_EVENTS = 'sylius.shop.layout.';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CanonicalUrlGeneratorInterface $canonicalUrls,
        private ContentTypeRegistryInterface $contentTypes,
        private LayoutRegistryInterface $layouts,
        private HomepageRepositoryInterface $homepages,
    ) {
    }

    public function generate(SlotMetadata $slot, ?object $sample, ChannelInterface $channel, string $localeCode): ?string
    {
        if ([] === $slot->provides) {
            return $this->isOnTheHomepage($slot, $channel) ? $this->urlGenerator->generate('sylius_shop_homepage', ['_locale' => $localeCode], UrlGeneratorInterface::ABSOLUTE_URL) : null;
        }

        return match (true) {
            in_array('product', $slot->provides, true) && $sample instanceof ProductInterface => $this->page('sylius_shop_product_show', self::productSlug($sample, $localeCode), $localeCode),
            in_array('taxon', $slot->provides, true) && $sample instanceof TaxonInterface => $this->page('sylius_shop_product_index', self::taxonSlug($sample, $localeCode), $localeCode),
            in_array('entry', $slot->provides, true) && $sample instanceof EntryInterface && $this->rendersInItsLayout($sample, $slot) => $this->canonicalUrls->generate($sample, $channel, $localeCode),
            default => null,
        };
    }

    /**
     * Whether the slot is on the homepage: on every page of the shop's layout, or on Sylius's homepage while the
     * channel's homepage isn't a CMS page. As the admin's warning, a channel counts as soon as a page is its homepage
     */
    private function isOnTheHomepage(SlotMetadata $slot, ChannelInterface $channel): bool
    {
        if (str_starts_with((string) $slot->templateEvent, self::LAYOUT_EVENTS)) {
            return true;
        }

        return $slot->isOnStoreHomepage() && [] === $this->homepages->findByChannelCodes([(string) $channel->getCode()]);
    }

    /**
     * Whether the layout the entry renders in, its own or its content type's default, renders the slot
     */
    private function rendersInItsLayout(EntryInterface $entry, SlotMetadata $slot): bool
    {
        $layout = $this->layouts->resolve($this->contentTypes->getByEntry($entry)->code, $entry->getBlockTree()->layout?->code);

        return null !== $layout && $layout->renders($slot->identifier);
    }

    /**
     * The page of a product or a taxon, by its slug in the locale, or null when it has none there
     */
    private function page(string $route, ?string $slug, string $localeCode): ?string
    {
        if (null === $slug || '' === $slug) {
            return null;
        }

        return $this->urlGenerator->generate($route, ['_locale' => $localeCode, 'slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Read from the translation in the locale itself, as Sylius's getTranslation() falls back to another locale's
     */
    private static function productSlug(ProductInterface $product, string $localeCode): ?string
    {
        $translation = $product->getTranslations()->get($localeCode);

        return $translation instanceof ProductTranslationInterface ? $translation->getSlug() : null;
    }

    private static function taxonSlug(TaxonInterface $taxon, string $localeCode): ?string
    {
        $translation = $taxon->getTranslations()->get($localeCode);

        return $translation instanceof TaxonTranslationInterface ? $translation->getSlug() : null;
    }
}
