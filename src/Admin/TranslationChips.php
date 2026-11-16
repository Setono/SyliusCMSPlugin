<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The translation chips of an entry's row, one per locale of the entry's channels, which the Translations column of the
 * entry lists and the Translations section of the overview page show through cms_admin_translation_chips(). They're
 * the working copy's (ADR 0009), read from the draft the row was loaded with. See "Translation chips" in
 * docs/spec/admin.md
 */
final class TranslationChips implements ResetInterface
{
    /** @var array<string, list<string>>|null channel code => its locale codes, its default locale first and then the others by code */
    private ?array $channelLocales = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly WorkingCopyProviderInterface $workingCopies,
        private readonly TranslatableValues $translatableValues,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    /**
     * @return list<TranslationChip> in the order the store lists its channels and their locales
     */
    public function of(EntryRow $row): array
    {
        $workingCopy = $this->workingCopies->getWorkingCopyWithDraft($row->entry, $row->draft);
        $translations = $workingCopy->getTranslations();

        $chips = [];
        foreach ($this->localesOf($row->entry->getChannelCodes()) as $localeCode) {
            $translation = $translations->get($localeCode);
            if (!$translation instanceof EntryTranslationInterface || null === $translation->getTitle() || '' === $translation->getTitle()) {
                $chips[] = new TranslationChip($localeCode, false);

                continue;
            }

            $completeness = $this->translatableValues->completeness($workingCopy, $localeCode);
            $chips[] = new TranslationChip($localeCode, true, !$translation->isShown(), $completeness->untranslated, $completeness->outdated);
        }

        return $chips;
    }

    public function reset(): void
    {
        $this->channelLocales = null;
    }

    /**
     * The locales of the channels, each once
     *
     * @param list<string> $channelCodes
     *
     * @return list<string>
     */
    private function localesOf(array $channelCodes): array
    {
        $locales = [];
        foreach ($this->channelLocales() as $channelCode => $localeCodes) {
            if (in_array($channelCode, $channelCodes, true)) {
                $locales = [...$locales, ...$localeCodes];
            }
        }

        return array_values(array_unique($locales));
    }

    /**
     * The store's channels, as the builder orders them, with their locales. Read once per request
     *
     * @return array<string, list<string>>
     */
    private function channelLocales(): array
    {
        if (null !== $this->channelLocales) {
            return $this->channelLocales;
        }

        $this->channelLocales = [];
        foreach ($this->channelRepository->findBy([], ['id' => 'ASC']) as $channel) {
            $localeCodes = [];
            if ($channel instanceof CoreChannelInterface) {
                foreach ($channel->getLocales() as $locale) {
                    $localeCodes[] = (string) $locale->getCode();
                }
                sort($localeCodes);

                $default = $channel->getDefaultLocale()?->getCode();
                if (null !== $default && in_array($default, $localeCodes, true)) {
                    $localeCodes = [$default, ...array_values(array_diff($localeCodes, [$default]))];
                }
            }

            $this->channelLocales[(string) $channel->getCode()] = $localeCodes;
        }

        return $this->channelLocales;
    }
}
