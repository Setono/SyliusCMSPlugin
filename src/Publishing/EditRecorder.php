<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Security\EditorName;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Records on an entry who saved it and when, for the admin's "Last edited". Every save calls it: creating an entry,
 * autosaving, discarding the draft and saving on-save settings. It's written with the save
 *
 * @internal
 */
final readonly class EditRecorder
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ClockInterface $clock,
    ) {
    }

    public function record(EntryInterface $entry): void
    {
        $entry->setLastEdited($this->clock->now(), EditorName::signedIn($this->tokenStorage));
    }
}
