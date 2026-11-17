<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkCreatorInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUnavailableException;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a share link of the entry's draft for the channel and the locale in the body, `{channel, locale}`, with Shown to
 * and Date fixed now, and answers with it. For a snippet, the body may also name one of its placements, `placement`,
 * and the ref of a sample object, `sample`, which the link fixes. See "Share links" in docs/spec/publishing.md
 */
final readonly class CreateShareLinkAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private ShareLinkCreatorInterface $creator,
        private ShareLinkAnswer $answer,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, fn (EntryInterface $entry): JsonResponse => $this->create($request, $entry));
    }

    private function create(Request $request, EntryInterface $entry): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $channelCode = $body['channel'] ?? null;
        $localeCode = $body['locale'] ?? null;
        if (!is_string($channelCode) || !is_string($localeCode)) {
            return BuilderRequest::badRequest('The body needs the channel and the locale, each as a string');
        }

        $placementUuid = $body['placement'] ?? null;
        $sampleRef = $body['sample'] ?? null;
        if ((null !== $placementUuid && !is_string($placementUuid)) || (null !== $sampleRef && !is_string($sampleRef))) {
            return BuilderRequest::badRequest('The placement and the sample must each be a string or null');
        }

        try {
            $shareLink = $this->creator->create($entry, $channelCode, $localeCode, $placementUuid, $sampleRef);
        } catch (ShareLinkUnavailableException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        return new JsonResponse(CanonicalJson::encode(($this->answer)($shareLink)), Response::HTTP_CREATED, json: true);
    }
}
