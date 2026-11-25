<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Admin\AdminTimeZone;
use Setono\SyliusCMSPlugin\Admin\EntryPlacement;
use Setono\SyliusCMSPlugin\Admin\EntryPlacements;
use Setono\SyliusCMSPlugin\Builder\MediaLibraryConfig;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preview\StartingValuesInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The builder's full-screen page, which loads the prebuilt builder app with its config. See docs/spec/builder.md
 */
final readonly class BuilderAction
{
    /** What the endpoints that name another entry hold in place of its UUID, which the builder replaces */
    public const UUID_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

    /** The flash type of what the builder tells the editor once it opens, such as what creating from a starting point left out */
    public const NOTICES = 'setono_sylius_cms_builder_notice';

    /** A path and query of URL characters only (RFC 3986) */
    private const URL_CHARACTERS = '#^[A-Za-z0-9\-._~!$&\'()*+,;=:@/%?]*\z#';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     * @param list<string> $scripts the scripts of the field controls of projects' own field types, from
     *                              setono_sylius_cms.builder.scripts, which the template resolves through asset()
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private BuilderCsrfToken $csrfToken,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private Environment $twig,
        private ChannelRepositoryInterface $channelRepository,
        private RepositoryInterface $customerGroupRepository,
        private FallbackChainInterface $fallbackChain,
        private StartingValuesInterface $startingValues,
        private MediaLibraryConfig $mediaLibraryConfig,
        private EntryPlacements $entryPlacements,
        private ContentTypeRegistryInterface $contentTypes,
        private AdminTimeZone $timeZone,
        private array $scripts = [],
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid);
        // An entry of a content type switched off in setono_sylius_cms.disabled.content_types is hidden from the builder
        if (null === $entry || $this->contentTypes->getByEntry($entry)->disabled) {
            throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));
        }

        $channels = $this->channels();
        $locales = $this->locales($channels, $request->getLocale());

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/builder.html.twig', [
            'entry' => $entry,
            'scripts' => $this->scripts,
            'config' => [
                'entryUuid' => $entry->getUuid(),
                'locale' => $this->editedLocale($request, $entry, $locales),
                // The block it opens at, such as where a problem is fixed, which the app selects when the tree has it
                'block' => self::query($request, 'block'),
                // What creating the entry from a starting point left out, which the app tells the editor once
                'notices' => self::notices($request),
                'csrfToken' => $this->csrfToken->value(),
                'backUrl' => $this->backUrl($request, $entry),
                'overviewUrl' => $this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $entry->getUuid()]),
                'endpoints' => [
                    'registry' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_registry'),
                    'entry' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_show', ['uuid' => $entry->getUuid()]),
                    'validate' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_validate', ['uuid' => $entry->getUuid()]),
                    'publish' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_publish', ['uuid' => $entry->getUuid()]),
                    'discard' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_discard', ['uuid' => $entry->getUuid()]),
                    'schedule' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_schedule', ['uuid' => $entry->getUuid()]),
                    'settings' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_settings', ['uuid' => $entry->getUuid()]),
                    'previewToken' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preview_token'),
                    'previewRender' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preview_render'),
                    'presence' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_presence', ['uuid' => $entry->getUuid()]),
                    'slug' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_slug'),
                    'urls' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_urls', ['uuid' => $entry->getUuid()]),
                    // What pickers search, with __kind__ for the kind, such as product or a link target's code
                    'search' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_search', ['kind' => '__kind__']),
                    // How many items a source yields for a list field's parameters, under the preview's token
                    'sourceCount' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_source_count'),
                    'cardDisplays' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_card_displays'),
                    // The blocks a Snippet block shows, which Detach copies, and the builder its Edit snippet opens,
                    // each with UUID_PLACEHOLDER for the snippet's UUID
                    'snippetBlocks' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_snippet_blocks', ['uuid' => self::UUID_PLACEHOLDER]),
                    'builder' => $this->urlGenerator->generate('setono_sylius_cms_admin_builder', ['uuid' => self::UUID_PLACEHOLDER]),
                    // What the top bar shows of a snippet's preview, and the sample objects it can show it with, under
                    // the preview's token
                    'previewSnippet' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preview_snippet'),
                    'previewSamples' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preview_samples'),
                    // The block presets at a spot in the entry's tree, for the Presets tab, and a preset's blocks ready to
                    // insert, with UUID_PLACEHOLDER for the preset's id, a code preset's code or an editor preset's UUID
                    'presets' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preset_index', ['entry' => $entry->getUuid()]),
                    'presetInsert' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preset_insert', ['preset' => self::UUID_PLACEHOLDER]),
                    // ★ saves blocks as an editor preset, and an editor preset is replaced, renamed, deleted and
                    // downloaded, with UUID_PLACEHOLDER for its UUID
                    'presetCreate' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preset_create'),
                    'preset' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preset_update', ['uuid' => self::UUID_PLACEHOLDER]),
                    'presetExport' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preset_export', ['uuid' => self::UUID_PLACEHOLDER]),
                ],
                'channels' => array_map(static fn (array $channel): array => [
                    'code' => $channel['channel']->getCode(),
                    'name' => $channel['channel']->getName(),
                    'locales' => array_keys($channel['locales']),
                ], $channels),
                'locales' => $locales,
                'customerGroups' => $this->customerGroups(),
                // Every date and time is entered and shown in the admin's time zone, whatever the editor's browser uses.
                // See "Dates and times" in docs/spec/builder.md
                'timeZone' => $this->timeZone->name(),
                // The media picker is the media library opened as a picker, with the library's endpoints, and the
                // store's locales for the alt text. The registry gives it the upload limit and types
                'media' => $this->mediaLibraryConfig->of($request->getLocale()),
                // A snippet's placements, which the builder lists without changing them, or null for another entry
                'placements' => $this->placements($entry),
                // Where the preview's Shown to and Date start. Date is a UTC date and time, or null for now
                'preview' => [
                    'shownTo' => $this->startingValues->shownTo($entry),
                    'date' => $this->startingValues->date($entry)?->format('Y-m-d\TH:i:s\Z'),
                ],
                // The rest of the UI strings arrive with the registry, so the app shows these until it has loaded
                'strings' => [
                    'setono_sylius_cms.builder.loading' => $this->translator->trans('setono_sylius_cms.builder.loading'),
                    'setono_sylius_cms.builder.load_failed' => $this->translator->trans('setono_sylius_cms.builder.load_failed'),
                ],
            ],
        ]));
    }

    /**
     * The builder edits one locale at a time, and opens in the locale the URL gives when the locale switcher offers it,
     * such as where a problem in a translatable value is fixed. Otherwise it opens in the store's default locale, or else
     * the entry's first translation. The locale switcher picks another
     *
     * @param list<array{code: string, name: string, fallbackChain: list<string>}> $locales the locales the switcher offers
     */
    private function editedLocale(Request $request, EntryInterface $entry, array $locales): string
    {
        $requested = self::query($request, 'locale');
        if (null !== $requested && in_array($requested, array_column($locales, 'code'), true)) {
            return $requested;
        }

        $default = $this->translationLocaleProvider->getDefaultLocaleCode();
        $locales = $entry->getTranslations()->getKeys();

        return in_array($default, $locales, true) || [] === $locales ? $default : (string) $locales[0];
    }

    /**
     * The store's channels, which the settings pick the entry's channels from, with their locales, the channel's default
     * locale first and then the others by code. The preview picks from the entry's channels, and the locale switcher
     * offers their locales
     *
     * @return list<array{channel: ChannelInterface, locales: array<string, LocaleInterface>}>
     */
    private function channels(): array
    {
        return array_values(array_map(static function (ChannelInterface $channel): array {
            $byCode = [];
            if ($channel instanceof CoreChannelInterface) {
                foreach ($channel->getLocales() as $locale) {
                    $byCode[(string) $locale->getCode()] = $locale;
                }
            }

            $locales = [];
            foreach (ChannelLocales::of($channel) as $code) {
                $locales[$code] = $byCode[$code];
            }

            return ['channel' => $channel, 'locales' => $locales];
        }, $this->channelRepository->findBy([], ['id' => 'ASC'])));
    }

    /**
     * Every locale the locale switcher can offer, those of the store's channels in their order, with its name in the
     * admin user's locale and its fallback chain
     *
     * @param list<array{channel: ChannelInterface, locales: array<string, LocaleInterface>}> $channels
     *
     * @return list<array{code: string, name: string, fallbackChain: list<string>}>
     */
    private function locales(array $channels, string $displayLocale): array
    {
        $locales = [];
        foreach ($channels as $channel) {
            foreach ($channel['locales'] as $code => $locale) {
                $locales[$code] ??= ['code' => $code, 'name' => $locale->getName($displayLocale) ?? $code, 'fallbackChain' => $this->fallbackChain->localesFor($code)];
            }
        }

        return array_values($locales);
    }

    /**
     * The store's customer groups, which the audience picks from, beside guests and customers without a group
     *
     * @return list<array{code: string|null, name: string|null}>
     */
    private function customerGroups(): array
    {
        return array_values(array_map(
            static fn (CustomerGroupInterface $group): array => ['code' => $group->getCode(), 'name' => $group->getName()],
            $this->customerGroupRepository->findBy([], ['name' => 'ASC']),
        ));
    }

    /**
     * A snippet's placements, by slot as its overview page lists them, each with its slot's label in the admin user's
     * locale, or the slot's identifier when the code no longer declares it. Null for an entry that isn't a snippet
     *
     * @return list<array{uuid: string, slot: string, label: string, removed: bool}>|null
     */
    private function placements(EntryInterface $entry): ?array
    {
        $section = $this->entryPlacements->of($entry);
        if (null === $section) {
            return null;
        }

        return array_map(fn (EntryPlacement $placed): array => [
            'uuid' => $placed->placement->getUuid(),
            'slot' => $placed->placement->getSlot(),
            'label' => null === $placed->slot ? $placed->placement->getSlot() : $this->translator->trans($placed->slot->label),
            'removed' => null === $placed->slot,
        ], $section->placements);
    }

    /**
     * The notices waiting for the builder, which are read once
     *
     * @return list<string>
     */
    private static function notices(Request $request): array
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return [];
        }

        return array_values(array_filter($session->getFlashBag()->get(self::NOTICES), is_string(...)));
    }

    private static function query(Request $request, string $name): ?string
    {
        $value = $request->query->all()[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Back, and Esc, return to where the builder was opened from, when that's a path in the admin passed as `back`, and
     * otherwise to the entry's overview page, which the builder opens from, as it does straight after the entry's created.
     * See "Into and out of the builder" in docs/spec/admin.md
     */
    private function backUrl(Request $request, EntryInterface $entry): string
    {
        $overview = $this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $entry->getUuid()]);
        // The admin is where the overview page is, such as /admin/
        $admin = substr($overview, 0, (int) strpos($overview, '/', 1) + 1);

        $back = $request->query->get('back');
        // Only URL characters: a browser skips a tab or a line break, so "/<tab>/evil.example" would leave the shop
        if (is_string($back) && str_starts_with($back, $admin) && 1 === preg_match(self::URL_CHARACTERS, $back)) {
            return $back;
        }

        return $overview;
    }
}
