<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\Routing\UrlClash;
use Setono\SyliusCMSPlugin\Routing\UrlClashFinderInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Messages name a URL as the shop shows it, /{locale}/{prefix}/{slug}, and channels by name. A URL another route takes
 * is named once per locale, by the first channel it's taken in
 */
final readonly class EntryUrlValidator implements EntryUrlValidatorInterface
{
    public const FIELD = 'slug';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channels
     */
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private UrlClashFinderInterface $clashes,
        private TakenUrlFinderInterface $takenUrls,
        private ChannelRepositoryInterface $channels,
        private TranslatorInterface $translator,
        private Availability $availability,
    ) {
    }

    public function validate(EntryInterface $entry): array
    {
        if (!$this->contentTypes->getByEntry($entry)->hasOwnUrl()) {
            return [];
        }

        $errors = $slugs = [];
        foreach ($this->slugsOf($entry) as $localeCode => $slug) {
            if (null !== $slug && Slug::isValid($slug)) {
                $slugs[$localeCode] = $slug;

                continue;
            }

            $errors[] = $this->error($localeCode, null === $slug || '' === $slug ? 'setono_sylius_cms.slug.blank' : 'setono_sylius_cms.slug.invalid');
        }

        $channelCodes = $entry->getChannelCodes();

        return [
            ...$errors,
            ...array_values($this->takenByRoutes($this->takenUrls->find($entry, $slugs, $channelCodes), 'setono_sylius_cms.slug.taken_by_route')),
            ...$this->clashErrors($this->clashes->findPublished($entry, $slugs, $channelCodes), 'setono_sylius_cms.slug.taken'),
        ];
    }

    public function warnOfDraftedUrls(EntryInterface $entry): array
    {
        if (!$this->contentTypes->getByEntry($entry)->hasOwnUrl()) {
            return [];
        }

        return $this->clashErrors($this->clashes->findDrafted($entry, $this->validSlugsOf($entry), $entry->getChannelCodes()), 'setono_sylius_cms.slug.drafted_elsewhere');
    }

    public function warnOfLiveUrlsTakenByRoutes(EntryInterface $entry): array
    {
        if (!$this->contentTypes->getByEntry($entry)->hasOwnUrl()) {
            return [];
        }

        return array_values($this->takenByRoutes($this->takenUrls->findLive($entry), 'setono_sylius_cms.slug.live_url_taken_by_route'));
    }

    public function refuseUrlsTakenByRoutes(EntryInterface $entry, array $slugs): array
    {
        if (!$this->contentTypes->getByEntry($entry)->hasOwnUrl()) {
            return [];
        }

        // A slug that breaks the rules has no URL to take, and publish refuses it anyway
        return $this->takenByRoutes($this->takenUrls->find($entry, array_filter($slugs, Slug::isValid(...)), $entry->getChannelCodes()), 'setono_sylius_cms.slug.taken_by_route');
    }

    public function validateChannels(EntryInterface $entry, array $channelCodes): array
    {
        $added = array_values(array_diff($channelCodes, $entry->getChannelCodes()));
        if (!$entry->isPublished() || [] === $added || !$this->contentTypes->getByEntry($entry)->hasOwnUrl()) {
            return [];
        }

        return $this->clashErrors($this->clashes->findPublished($entry, $this->validSlugsOf($entry), $added), 'setono_sylius_cms.slug.taken_in_channel');
    }

    /**
     * @param list<TakenUrl> $takenUrls
     *
     * @return array<string, ValidationError> by locale
     */
    private function takenByRoutes(array $takenUrls, string $message): array
    {
        $errors = [];
        foreach ($takenUrls as $taken) {
            $errors[$taken->localeCode] ??= $this->error($taken->localeCode, $message, [
                '%url%' => self::url($taken->localeCode, $taken->path),
                '%route%' => $taken->route,
            ]);
        }

        return $errors;
    }

    /**
     * @param list<UrlClash> $clashes
     *
     * @return list<ValidationError>
     */
    private function clashErrors(array $clashes, string $message): array
    {
        if ([] === $clashes) {
            return [];
        }

        $names = $this->channelNames();

        return array_map(fn (UrlClash $clash): ValidationError => $this->error($clash->localeCode, $message, [
            '%url%' => self::url($clash->localeCode, $clash->path),
            '%title%' => $clash->other->title ?? $clash->other->uuid,
            '%channels%' => implode(', ', array_map(static fn (string $code): string => $names[$code] ?? $code, $clash->channelCodes)),
        ]), $clashes);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function error(string $localeCode, string $message, array $parameters = []): ValidationError
    {
        return new ValidationError(null, self::FIELD, $localeCode, $this->translator->trans($message, $parameters, 'validators'));
    }

    /**
     * @return array<string, string> channel code => its name, or its code when it has none
     */
    private function channelNames(): array
    {
        $names = [];
        foreach ($this->channels->findAll() as $channel) {
            $code = $channel->getCode();
            if (null !== $code) {
                $names[$code] = $channel->getName() ?? $code;
            }
        }

        return $names;
    }

    /**
     * The slug of each translation, by locale code, null where a translation has none. A translation without a title
     * is no translation (docs/spec/localization.md), such as one whose adding was undone in the builder, so it has no
     * URL to check
     *
     * @return array<string, string|null>
     */
    private function slugsOf(EntryInterface $entry): array
    {
        $slugs = [];
        foreach ($entry->getTranslations() as $localeCode => $translation) {
            if ($this->availability->hasTranslation($entry, $localeCode)) {
                $slugs[$localeCode] = $translation->getSlug();
            }
        }
        ksort($slugs);

        return $slugs;
    }

    /**
     * The slugs that follow the slug rules, by locale code, which have a URL to check: those of the translations with a
     * title
     *
     * @return array<string, string>
     */
    private function validSlugsOf(EntryInterface $entry): array
    {
        return Slug::of($entry, fn (string $localeCode): bool => $this->availability->hasTranslation($entry, $localeCode));
    }

    private static function url(string $localeCode, string $path): string
    {
        return sprintf('/%s/%s', $localeCode, $path);
    }
}
