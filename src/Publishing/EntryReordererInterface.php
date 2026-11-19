<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

interface EntryReordererInterface
{
    /**
     * Puts entries of a content type ordered by position in a new order, as editors drag them into it in the admin list.
     * They take the places they had between them, so the content type's other entries keep theirs. The positions are
     * on-save settings: they take effect at once, outside the draft. Each entry whose position changes has its
     * changedAt bumped, and, once the transaction commits, EntrySettingsChanged dispatched with position
     *
     * @param list<string> $uuids the entries' UUIDs in their new order
     *
     * @throws InvalidEntrySettingsException when the content type isn't ordered by position, or an entry isn't one of its
     */
    public function reorder(string $contentType, array $uuids): void;
}
