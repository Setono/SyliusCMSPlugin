<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The working copy (the draft, or the rows of an entry without one) in the entry format, with the lock token a save
 * made on it carries, the entry's on-save settings, which aren't part of the draft, its state, and who else has it open
 */
final readonly class ShowEntryAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private AutosaveInterface $autosave,
        private WorkingCopyAnswer $answer,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $entry = $this->builderRequest->entry($uuid);

        return ($this->answer)($entry, $this->autosave->load($entry));
    }
}
