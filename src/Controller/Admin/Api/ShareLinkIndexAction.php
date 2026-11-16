<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The entry's share links, newest first. See "Share links" in docs/spec/publishing.md
 */
final readonly class ShareLinkIndexAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private ShareLinkRepositoryInterface $shareLinks,
        private ShareLinkAnswer $answer,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $entry = $this->builderRequest->entry($uuid);

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'shareLinks' => array_map($this->answer, $this->shareLinks->findByEntry($entry)),
        ])), json: true);
    }
}
