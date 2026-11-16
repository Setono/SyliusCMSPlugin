<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the given blocks of the working copy, as the builder holds them, under the preview token in the query
 * parameter _cms_preview, and answers with their HTML by key. The token puts the request in preview mode, so the
 * blocks render through the same pipeline as the shop page in the preview, with their markers. A block that renders
 * nothing, such as one of a type the code no longer has or one its visibility conditions hide, answers an empty string.
 * See "Updating the preview" in docs/spec/builder.md
 */
final readonly class PreviewRenderAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
        private RenderContextFactoryInterface $renderContextFactory,
        private BlockViewBuilder $blockViewBuilder,
        private BlockRendererInterface $blockRenderer,
        private StoredDataUpgraderInterface $upgrader,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, fn (): JsonResponse => $this->render($request));
    }

    private function render(Request $request): JsonResponse
    {
        $preview = $this->previewContext->getPreview();
        if (null === $preview) {
            return BuilderRequest::badRequest('The request has no valid preview token');
        }

        try {
            $blocks = $request->toArray()['blocks'] ?? null;
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        if (!is_array($blocks) || !array_is_list($blocks)) {
            return BuilderRequest::badRequest('The body needs the blocks to render, as a list');
        }

        try {
            // The builder may hold blocks saved with an older version, such as across a deploy
            $blocks = array_map((new StoredTreeSerializer())->denormalizeBlock(...), array_values($this->upgrader->upgradeBlocks($blocks)->data));
        } catch (InvalidStoredTreeException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $workingCopy = $this->previewedEntry->getPreviewedContent($preview->localeCode) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $preview->entryUuid));
        $renderContext = $this->renderContextFactory->create(['entry' => $workingCopy]);

        $html = [];
        foreach ($blocks as $block) {
            $html[$block->key] = '';
        }

        foreach ($this->blockViewBuilder->build($blocks, $renderContext) as $view) {
            $html[$view->key] = $this->blockRenderer->render($view, $renderContext);
        }

        return new JsonResponse(['blocks' => (object) $html]);
    }
}
