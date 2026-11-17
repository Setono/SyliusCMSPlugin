<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * A plain shop page that shows the previewed snippet's block tree in the shop's layout, which the builder previews a
 * snippet on: a snippet has no URL of its own. It answers only a preview of a snippet, and 404 to anyone else. #260
 * previews a snippet on the page of one of its placements, and keeps this page for a snippet with none.
 * See "Snippet preview" in docs/spec/builder.md
 */
final readonly class SnippetPreviewAction
{
    public const ROUTE = 'setono_sylius_cms_shop_snippet_preview';

    public const TEMPLATE = '@SetonoSyliusCMSPlugin/shop/snippet_preview.html.twig';

    public function __construct(
        private PreviewedEntryProvider $previewedEntryProvider,
        private RenderContextFactoryInterface $renderContextFactory,
        private StoredDataUpgraderInterface $upgrader,
        private BlockViewBuilder $blockViewBuilder,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $snippet = $this->previewedEntryProvider->getPreviewedContent($request->attributes->getString('_locale'));
        if (!$snippet instanceof SnippetInterface) {
            throw new NotFoundHttpException('Only the preview of a snippet shows here');
        }

        $context = $this->renderContextFactory->create();

        return new Response($this->twig->render(self::TEMPLATE, [
            'snippet' => $snippet,
            'blocks' => $this->blockViewBuilder->build($this->upgrader->readBlockTree($snippet)->blocks, $context),
            'context' => $context->getHostPageContext(),
            CMSRuntime::RENDER_CONTEXT => $context,
        ]));
    }
}
