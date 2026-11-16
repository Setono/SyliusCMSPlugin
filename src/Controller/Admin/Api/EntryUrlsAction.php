<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Admin\EntryUrls;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * What the builder's URL & SEO tab shows beside the slug, as it is on the server: the warnings, which point at the slug
 * in a locale as validation errors do (URLs other entries have drafted, and published URLs another route takes now),
 * and the URLs of the old slugs that redirect, by locale. It changes nothing, so it needs no CSRF token.
 * See "Right pane: the selection" in docs/spec/builder.md
 */
final readonly class EntryUrlsAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private EntryUrlValidatorInterface $urlValidator,
        private EntryUrls $entryUrls,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $entry = $this->builderRequest->entry($uuid);
        $warnings = [...$this->urlValidator->warnOfDraftedUrls($this->workingCopies->getWorkingCopy($entry)), ...$this->urlValidator->warnOfLiveUrlsTakenByRoutes($entry)];

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'warnings' => array_map(static fn (ValidationError $warning): ListedObject => new ListedObject($warning->jsonSerialize()), $warnings),
            'oldUrls' => (object) $this->entryUrls->oldUrlsOf($entry),
        ])), json: true);
    }
}
