<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Autosaves a change set. Until drafts arrive (#217), it writes straight to the rows
 */
final readonly class SaveEntryAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private EntryRepositoryInterface $entryRepository,
        private ChangeSetApplier $changeSetApplier,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        if (!$this->csrfToken->isValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $changeSet = ChangeSet::fromArray(CanonicalJson::decode($request->getContent()));
            $entry->setBlockTree($this->changeSetApplier->apply($changeSet, $entry->getBlockTree()));
        } catch (\JsonException|InvalidChangeSetException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->managerRegistry->getManagerForClass($entry::class)?->flush();

        return new JsonResponse(['status' => EntryStatus::of($entry)]);
    }
}
