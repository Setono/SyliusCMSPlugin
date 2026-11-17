<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Source\SourceResolver;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * How many items a source yields right now for the parameters in the body, {"source": code, "params": {…}}, under the
 * preview token in the query parameter _cms_preview, so in the preview's channel, locale, Shown to and Date, with the
 * previewed working copy as the page's entry. Its parameters' references load as they do before rendering. A source
 * the code no longer has yields nothing. It changes nothing, so it needs no CSRF token. See "Sources" and "JSON
 * endpoints" in docs/spec/builder.md
 */
final readonly class SourceCountAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
        private RenderContextFactoryInterface $renderContextFactory,
        private ReferenceLoaderInterface $referenceLoader,
        private SourceResolver $sourceResolver,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $preview = $this->previewContext->getPreview();
        if (null === $preview) {
            return BuilderRequest::badRequest('The request has no valid preview token');
        }

        try {
            $body = $request->toArray();
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $code = $body['source'] ?? null;
        $params = $body['params'] ?? [];
        if (!is_string($code) || '' === $code || !is_array($params) || ([] !== $params && array_is_list($params))) {
            return BuilderRequest::badRequest('The body needs the code of the source, and its parameters as a map');
        }

        $workingCopy = $this->previewedEntry->getPreviewedContent($preview->localeCode) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $preview->entryUuid));
        $renderContext = $this->renderContextFactory->create(['entry' => $workingCopy]);
        $references = $this->referenceLoader->load($this->sourceResolver->references($code, $params), $renderContext);

        return new JsonResponse(['count' => $this->sourceResolver->resolve($code, $params, null, new HydrationContext($renderContext, $references))->getNbResults()]);
    }
}
