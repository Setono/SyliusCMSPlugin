<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the given blocks of the working copy, as the builder holds them, under the preview token in the query
 * parameter _cms_preview, and answers with their HTML by key. The token puts the request in preview mode, so the
 * blocks render through the same pipeline as the shop page in the preview, with their markers. A block that renders
 * nothing, such as one of a type the code no longer has, answers an empty string. See "Updating the preview" in
 * docs/spec/builder.md
 */
final readonly class PreviewRenderAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private PreviewContext $previewContext,
        private EntryRepositoryInterface $entryRepository,
        private WorkingCopyProviderInterface $workingCopyProvider,
        private RenderContextFactoryInterface $renderContextFactory,
        private BlockViewBuilder $blockViewBuilder,
        private BlockRendererInterface $blockRenderer,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (!$this->csrfToken->isValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $preview = $this->previewContext->getPreview();
        if (null === $preview) {
            return self::badRequest('The request has no valid preview token');
        }

        try {
            $blocks = $request->toArray()['blocks'] ?? null;
        } catch (JsonException $e) {
            return self::badRequest($e->getMessage());
        }

        if (!is_array($blocks) || !array_is_list($blocks)) {
            return self::badRequest('The body needs the blocks to render, as a list');
        }

        try {
            $blocks = array_map((new StoredTreeSerializer())->denormalizeBlock(...), $blocks);
        } catch (InvalidStoredTreeException $e) {
            return self::badRequest($e->getMessage());
        }

        $entry = $this->entryRepository->findOneByUuid($preview->entryUuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $preview->entryUuid));
        $renderContext = $this->renderContextFactory->create(['entry' => $this->workingCopyProvider->getWorkingCopy($entry)]);

        $html = [];
        foreach ($blocks as $block) {
            $html[$block->key] = '';
        }

        foreach ($this->blockViewBuilder->build($blocks, $renderContext) as $view) {
            $html[$view->key] = $this->blockRenderer->render($view, $renderContext);
        }

        return new JsonResponse(['blocks' => (object) $html]);
    }

    private static function badRequest(string $error): JsonResponse
    {
        return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
    }
}
