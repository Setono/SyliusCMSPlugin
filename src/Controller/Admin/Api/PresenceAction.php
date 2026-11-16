<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Builder\PresenceTrackerInterface;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The builder says every few seconds that the editor still has the entry open, and gets who else does. The answer
 * also carries the working copy's lock token, so a builder that's behind pulls in what colleagues saved
 */
final readonly class PresenceAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private PresenceTrackerInterface $presence,
        private SavedChangeSetRepositoryInterface $savedChangeSets,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, function (EntryInterface $entry): JsonResponse {
            $admin = $this->builderRequest->admin();
            $this->presence->see($entry, $admin);

            return new JsonResponse(CanonicalJson::encode(new ListedObject([
                'editors' => WorkingCopyAnswer::editors($this->presence->others($entry, $admin)),
                'lockToken' => $this->savedChangeSets->findLatestLockToken($entry),
            ])), json: true);
        });
    }
}
