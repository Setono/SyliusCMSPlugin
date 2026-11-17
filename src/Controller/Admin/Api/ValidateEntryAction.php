<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Validates the working copy as publish would, and answers with its errors by block key and field, and by entry field.
 * It changes nothing, so it needs no CSRF token
 */
final readonly class ValidateEntryAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private EntryValidatorInterface $entryValidator,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $entry = $this->builderRequest->entry($uuid);

        return new JsonResponse(['errors' => $this->entryValidator->validate($entry, $this->workingCopies->getWorkingCopy($entry))]);
    }
}
