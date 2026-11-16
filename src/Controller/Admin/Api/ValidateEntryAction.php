<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Validates the working copy as publish would, and answers with its errors by block key and field, and by entry field.
 * It answers with the warnings the builder shows once a save lands too, in the same shape: URLs other entries have
 * drafted, and published URLs another route takes now. It changes nothing, so it needs no CSRF token
 */
final readonly class ValidateEntryAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private EntryValidatorInterface $entryValidator,
        private EntryUrlValidatorInterface $urlValidator,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $entry = $this->builderRequest->entry($uuid);
        $workingCopy = $this->workingCopies->getWorkingCopy($entry);

        return new JsonResponse([
            'errors' => $this->entryValidator->validate($workingCopy),
            'warnings' => [...$this->urlValidator->warn($workingCopy), ...$this->urlValidator->warnTakenLiveUrls($entry)],
        ]);
    }
}
