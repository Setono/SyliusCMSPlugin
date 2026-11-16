<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Homepage\HomepageResolverInterface;
use Setono\SyliusCMSPlugin\Rendering\LayoutRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorates Sylius's homepage controller, sylius.controller.shop.homepage, so its route, sylius_shop_homepage, and
 * every path('sylius_shop_homepage') in themes keep working. On a channel with a homepage the shopper can see,
 * /{locale}/ renders that page in its layout, as an entry renders at its own URL, without the URL lookup. Otherwise
 * Sylius's own homepage renders, so the homepage never answers 404. See "Rendering the homepage" in
 * docs/spec/urls-and-seo.md
 */
final readonly class HomepageAction
{
    /**
     * @param object $storeHomepage the controller it decorates, whose indexAction() renders Sylius's own homepage
     */
    public function __construct(
        private object $storeHomepage,
        private HomepageResolverInterface $homepageResolver,
        private RenderContextFactoryInterface $renderContextFactory,
        private LayoutRendererInterface $layoutRenderer,
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $page = $this->homepageResolver->resolve($this->renderContextFactory->create());
        if (null === $page) {
            return $this->renderStoreHomepage($request);
        }

        // The tree in the page's layout, with the page as host-page context
        return new Response($this->layoutRenderer->render($page, $this->renderContextFactory->create(['entry' => $page])));
    }

    private function renderStoreHomepage(Request $request): Response
    {
        $controller = $this->storeHomepage;
        if (!method_exists($controller, 'indexAction')) {
            throw new \LogicException(sprintf('The homepage controller it decorates, %s, has no indexAction()', $controller::class));
        }

        $response = $controller->indexAction($request);
        if (!$response instanceof Response) {
            throw new \LogicException(sprintf('The homepage controller it decorates, %s, must answer with a response', $controller::class));
        }

        return $response;
    }
}
