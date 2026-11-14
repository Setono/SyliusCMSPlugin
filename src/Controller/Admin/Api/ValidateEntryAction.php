<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Validates the working copy as publish would, and answers with its errors by block key and field, and by entry field.
 * It changes nothing, so it needs no CSRF token
 */
final readonly class ValidateEntryAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private WorkingCopyProviderInterface $workingCopies,
        private EntryValidatorInterface $entryValidator,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        return new JsonResponse(['errors' => $this->entryValidator->validate($this->workingCopies->getWorkingCopy($entry))]);
    }
}
