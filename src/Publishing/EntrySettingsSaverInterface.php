<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

interface EntrySettingsSaverInterface
{
    /**
     * Saves an entry's on-save settings, which take effect at once, outside the draft. When something changed, it bumps
     * changedAt and, after the transaction commits, dispatches EntrySettingsChanged with what changed
     *
     * @throws InvalidEntrySettingsException when it refuses the settings, such as channels where another entry has
     *                                       published one of the entry's published URLs
     */
    public function save(EntryInterface $entry, EntrySettings $settings): void;
}
