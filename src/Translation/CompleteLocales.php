<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The locales an entry's working copy is complete in: those it has a translation in, held back or not, with none of its
 * translatable values untranslated or outdated there, as a translation chip says complete. They're stored with each
 * entry, so the Overview's Translations counts them per channel and locale in SQL. See "Completeness and outdated
 * flags" in docs/spec/localization.md
 *
 * @internal
 */
final readonly class CompleteLocales
{
    public function __construct(
        private TranslatableValues $translatableValues,
        private Availability $availability,
    ) {
    }

    /**
     * @return list<string> by code
     */
    public function of(EntryInterface $workingCopy): array
    {
        $complete = [];
        foreach ($workingCopy->getTranslations() as $translation) {
            $localeCode = $translation->getLocale();
            if (null === $localeCode || !$this->availability->hasTranslation($workingCopy, $localeCode)) {
                continue;
            }

            $completeness = $this->translatableValues->completeness($workingCopy, $localeCode);
            if (0 === $completeness->untranslated && 0 === $completeness->outdated) {
                $complete[] = $localeCode;
            }
        }

        sort($complete);

        return $complete;
    }
}
