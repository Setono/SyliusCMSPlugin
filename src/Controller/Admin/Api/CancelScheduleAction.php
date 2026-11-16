<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cancels the entry's scheduled publish, which unlocks the draft. It answers like entry_show, so the builder loads the
 * working copy, unlocked. See "Scheduled publishes" in docs/spec/publishing.md
 */
final readonly class CancelScheduleAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private PublishSchedulerInterface $scheduler,
        private AutosaveInterface $autosave,
        private WorkingCopyAnswer $answer,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, function (EntryInterface $entry): JsonResponse {
            $this->scheduler->cancel($entry);

            return ($this->answer)($entry, $this->autosave->load($entry));
        });
    }
}
