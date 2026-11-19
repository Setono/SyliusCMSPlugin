<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Collection\PageNotFoundException;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\LayoutRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An entry's page in the shop, at its own URL or as a channel's homepage: the tree in the entry's layout, with the
 * entry as host-page context, at the page of its paginated Entries block that ?page asks for. Its URL without ?page is
 * page 1, so ?page=1 redirects there, and a page past the last answers 404. See "Pagination" in
 * docs/spec/collections.md
 *
 * @internal
 */
final readonly class EntryPage
{
    public function __construct(
        private RenderContextFactoryInterface $renderContextFactory,
        private LayoutRendererInterface $layoutRenderer,
    ) {
    }

    /**
     * @throws NotFoundHttpException for a page its paginated Entries block doesn't have
     */
    public function render(Request $request, EntryInterface $entry): Response
    {
        $redirect = RequestedPage::redirectFromPage1($request);
        if (null !== $redirect) {
            return $redirect;
        }

        $context = $this->renderContextFactory->create(EntryContext::objects($entry))->withPage(RequestedPage::of($request));

        try {
            return new Response($this->layoutRenderer->render($entry, $context));
        } catch (PageNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}
