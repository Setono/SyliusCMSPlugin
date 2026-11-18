<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Shop;

use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewedHostPageContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedSnippetProvider;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * A plain shop page that shows the previewed snippet in the shop's layout, with the preview's sample object as its
 * host-page context: the page of a snippet with no placement, and of a placement in a slot whose real page the plugin
 * doesn't know, such as the checkout's sidebar, which then renders here. It answers only a preview of a snippet, and
 * 404 to anyone else. See "Snippet preview" in docs/spec/builder.md
 */
final readonly class SnippetPreviewAction
{
    public const ROUTE = 'setono_sylius_cms_shop_snippet_preview';

    public const TEMPLATE = '@SetonoSyliusCMSPlugin/shop/snippet_preview.html.twig';

    public function __construct(
        private PreviewedSnippetProvider $previewedSnippet,
        private RenderContextFactoryInterface $renderContextFactory,
        private StoredDataUpgraderInterface $upgrader,
        private BlockViewBuilder $blockViewBuilder,
        private Environment $twig,
        private PreviewedHostPageContext $hostPageContext,
        private SlotRegistryInterface $slots,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $previewed = $this->previewedSnippet->get($request->attributes->getString('_locale'));
        if (null === $previewed) {
            throw new NotFoundHttpException('Only the preview of a snippet shows here');
        }

        $snippet = $previewed->snippet;
        $context = $this->renderContextFactory->create($this->hostPageContext->of($snippet));
        $slot = $this->slot($previewed->placement, array_keys($context->getHostPageContext()));

        return new Response($this->twig->render(self::TEMPLATE, [
            'snippet' => $snippet,
            'slot' => $slot,
            'blocks' => null === $slot ? $this->blockViewBuilder->build($this->upgrader->readBlockTree($snippet)->blocks, $context, entry: $snippet->getUuid()) : [],
            'context' => $context->getHostPageContext(),
            CMSRuntime::RENDER_CONTEXT => $context,
        ]));
    }

    /**
     * The slot of the previewed placement, which renders here when the code declares it and the sample object is all
     * the context it provides, or null
     *
     * @param list<string> $provided the context keys of the sample object
     */
    private function slot(?PlacementInterface $placement, array $provided): ?string
    {
        $slot = null === $placement ? null : $this->slots->find($placement->getSlot());
        if (null === $slot) {
            return null;
        }

        return [] === array_diff($slot->provides, $provided) ? $slot->identifier : null;
    }
}
