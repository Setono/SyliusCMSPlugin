<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;

/**
 * The translation chips of an entry's row, one per locale of the entry's channels, which the Translations column of the
 * entry lists and the Translations section of the overview page show through cms_admin_translation_chips(). They're
 * the working copy's (ADR 0009), read from the draft the row was loaded with. See "Translation chips" in
 * docs/spec/admin.md
 */
final readonly class TranslationChips
{
    public function __construct(
        private WorkingCopyProviderInterface $workingCopies,
        private TranslatableValues $translatableValues,
        private ChannelLocales $channelLocales,
        private Availability $availability,
    ) {
    }

    /**
     * @return list<TranslationChip> in the order the store lists its channels and their locales
     */
    public function of(EntryRow $row): array
    {
        $workingCopy = $this->workingCopies->getWorkingCopyWithDraft($row->entry, $row->draft);

        $chips = [];
        foreach ($this->channelLocales->ofChannels($row->entry->getChannelCodes()) as $localeCode) {
            if (!$this->availability->hasTranslation($workingCopy, $localeCode)) {
                $chips[] = new TranslationChip($localeCode, false, false, 0, 0);

                continue;
            }

            $completeness = $this->translatableValues->completeness($workingCopy, $localeCode);
            $chips[] = new TranslationChip($localeCode, true, !$this->availability->showsIn($workingCopy, $localeCode), $completeness->untranslated, $completeness->outdated);
        }

        return $chips;
    }
}
