<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preview\HostPageUrlGenerator;
use Setono\SyliusCMSPlugin\Preview\PreviewRenderContextFactory;
use Setono\SyliusCMSPlugin\Preview\SampleCandidatesInterface;
use Setono\SyliusCMSPlugin\Preview\SampleObjects;
use Setono\SyliusCMSPlugin\Preview\SnippetHostPages;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * For unit tests: where a snippet's preview and share links show it, as the container builds it, with the shop's
 * product, taxon, homepage and plain preview routes, and the stock slots product.after_price, taxon.header and shop.top
 */
final class SnippetPreviews
{
    private function __construct()
    {
    }

    /**
     * @param ReferenceLoaderInterface|null $referenceLoader what loads the sample objects, or null for nothing
     * @param list<string> $candidates the refs of the candidates for a starting sample object
     */
    public static function hostPages(
        PlacementRepositoryInterface $placements,
        ?ReferenceLoaderInterface $referenceLoader = null,
        array $candidates = [],
        ?UrlGeneratorInterface $urlGenerator = null,
    ): SnippetHostPages {
        $urlGenerator ??= self::router();

        return new SnippetHostPages(
            $placements,
            new SlotRegistry([
                'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => null, 'template_event' => ['name' => 'sylius.shop.layout.header', 'priority' => 30]],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
                'taxon.header' => ['label' => 'setono_sylius_cms.slot.taxon.header', 'provides' => ['taxon'], 'cap' => null],
            ]),
            self::samples($referenceLoader, $candidates),
            new HostPageUrlGenerator($urlGenerator, new class() implements CanonicalUrlGeneratorInterface {
                public function generate(EntryInterface $entry, ChannelInterface $channel, string $localeCode, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): ?string
                {
                    return null;
                }
            }),
            $urlGenerator,
        );
    }

    /**
     * @param list<string> $candidates the refs of the candidates for a starting sample object
     */
    public static function samples(?ReferenceLoaderInterface $referenceLoader = null, array $candidates = []): SampleObjects
    {
        return new SampleObjects(
            new class($candidates) implements SampleCandidatesInterface {
                /**
                 * @param list<string> $candidates
                 */
                public function __construct(private readonly array $candidates)
                {
                }

                public function refs(string $key, array $pageScope, RenderContext $context): iterable
                {
                    return $this->candidates;
                }
            },
            $referenceLoader ?? new ReferenceLoader(new ServiceLocator([])),
            Conditions::matcher(),
        );
    }

    public static function renderContexts(): PreviewRenderContextFactory
    {
        return new PreviewRenderContextFactory(new MockClock('2026-11-14 10:00:00 UTC'));
    }

    /**
     * The shop's routes a snippet's page is on, on the host
     */
    public static function router(string $host = 'shop.example'): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('sylius_shop_homepage', new Route('/{_locale}/'));
        $routes->add('sylius_shop_product_show', new Route('/{_locale}/products/{slug}'));
        $routes->add('sylius_shop_product_index', new Route('/{_locale}/taxons/{slug}', requirements: ['slug' => '.+']));
        $routes->add('setono_sylius_cms_shop_snippet_preview', new Route('/{_locale}/_cms/snippet-preview'));
        $routes->add('setono_sylius_cms_shop_entry', new Route('/{_locale}/{path}', requirements: ['path' => '.*']));

        return new UrlGenerator($routes, new RequestContext(host: $host, scheme: 'https'));
    }
}
