<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Discards the draft, so the entry goes back to its published content. It answers like entry_show,
 * so the builder loads the published content again. While a scheduled publish locks the draft, it answers 423
 */
final readonly class DiscardEntryAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private AutosaveInterface $autosave,
        private WorkingCopyAnswer $answer,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, function (EntryInterface $entry): JsonResponse {
            try {
                return ($this->answer)($entry, $this->autosave->discard($entry));
            } catch (DraftLockedException $e) {
                return BuilderRequest::draftLocked($e);
            }
        });
    }
}
