<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionPreviewUnavailableException;
use Setono\SyliusCMSPlugin\Revision\RevisionPreviewUrlGenerator;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates a read-only preview token for a revision, and answers with the URL of its page in the shop. The body can name
 * the channel and the locale, `{channel, locale}`. Without them, the preview is in the entry's first channel and the
 * first of its locales the revision has a URL in. See "Revisions" in docs/spec/publishing.md
 */
final readonly class PreviewRevisionAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private RevisionRepositoryInterface $revisions,
        private RevisionPreviewUrlGenerator $previewUrlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        return $this->builderRequest->withCsrfToken($request, fn (): JsonResponse => $this->preview($request, $id));
    }

    private function preview(Request $request, string $id): JsonResponse
    {
        try {
            $body = '' === $request->getContent() ? [] : $request->toArray();
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $channelCode = $body['channel'] ?? null;
        $localeCode = $body['locale'] ?? null;
        if ((null !== $channelCode && !is_string($channelCode)) || (null !== $localeCode && !is_string($localeCode))) {
            return BuilderRequest::badRequest('The channel and the locale must be strings when they\'re given');
        }

        $revision = $this->revisions->findOneById((int) $id) ?? throw new NotFoundHttpException(sprintf('There is no revision %s', $id));

        try {
            return new JsonResponse(['url' => $this->previewUrlGenerator->generate($revision, $channelCode, $localeCode)]);
        } catch (RevisionPreviewUnavailableException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }
    }
}
