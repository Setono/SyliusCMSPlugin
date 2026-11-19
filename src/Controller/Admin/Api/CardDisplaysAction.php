<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Collection\CardDisplays;
use Setono\SyliusCMSPlugin\Collection\ListedContentTypes;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewedHostPageContext;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The displays the Entries block offers for what its entries field holds, the body's {"entries": …}, which the
 * builder's display select offers: those the content type a source lists has cards for, those every picked entry's
 * content type has, or every display when it can't tell. It reads under the preview token in the query parameter
 * _cms_preview, with the previewed working copy as the page's entry, or a snippet's sample object, so In this category
 * and Related tell the content type they list on that page, and the picked entries are those the preview shows. It
 * changes nothing, so it needs no CSRF token. See "The Entries block" in docs/spec/collections.md and "JSON endpoints"
 * in docs/spec/builder.md
 */
final readonly class CardDisplaysAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
        private RenderContextFactoryInterface $renderContextFactory,
        private ReferenceLoaderInterface $referenceLoader,
        private PreviewedHostPageContext $hostPageContext,
        private ListedContentTypes $listedContentTypes,
        private CardDisplays $cardDisplays,
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
            $entries = $request->toArray()['entries'] ?? null;
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $workingCopy = $this->previewedEntry->getPreviewedContent($preview->localeCode) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $preview->entryUuid));
        $renderContext = $this->renderContextFactory->create($this->hostPageContext->of($workingCopy));
        $references = $this->referenceLoader->load($this->listedContentTypes->references($entries), $renderContext);

        return new JsonResponse(['displays' => $this->cardDisplays->offeredFor($this->listedContentTypes->of($entries, new HydrationContext($renderContext, $references)))]);
    }
}
