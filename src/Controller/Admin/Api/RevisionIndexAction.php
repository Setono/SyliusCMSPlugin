<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The entry's revisions, newest first, with who published each, when, and whether a scheduled publish did.
 * See "Revisions" in docs/spec/publishing.md
 */
final readonly class RevisionIndexAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private RevisionRepositoryInterface $revisions,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $entry = $this->builderRequest->entry($uuid);

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'revisions' => array_map(static fn (RevisionInterface $revision): ListedObject => new ListedObject([
                'id' => $revision->getId(),
                'publishedAt' => $revision->getPublishedAt()->format('Y-m-d\TH:i:s\Z'),
                'publishedBy' => $revision->getPublishedBy(),
                'scheduled' => $revision->isScheduled(),
            ]), $this->revisions->findByEntry($entry)),
        ])), json: true);
    }
}
