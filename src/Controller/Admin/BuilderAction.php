<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The builder's full-screen page, which loads the prebuilt builder app with its config. See docs/spec/builder.md
 */
final readonly class BuilderAction
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
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
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));
        $channels = $this->channels();
        $locales = $this->locales($channels, $request->getLocale());

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/builder.html.twig', [
            'entry' => $entry,
            'config' => [
                'entryUuid' => $entry->getUuid(),
                'locale' => $this->editedLocale($request, $entry, $locales),
                // The block it opens at, such as where a problem is fixed, which the app selects when the tree has it
                'block' => self::query($request, 'block'),
                'csrfToken' => $this->csrfToken->value(),
                'backUrl' => $this->backUrl($request, $entry),
                'overviewUrl' => $this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $entry->getUuid()]),
                'endpoints' => [
                    'registry' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_registry'),
                    'entry' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_show', ['uuid' => $entry->getUuid()]),
                    'validate' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_validate', ['uuid' => $entry->getUuid()]),
                    'publish' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_publish', ['uuid' => $entry->getUuid()]),
                    'discard' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_discard', ['uuid' => $entry->getUuid()]),
                    'settings' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_settings', ['uuid' => $entry->getUuid()]),
                    'previewToken' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preview_token'),
                    'previewRender' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_preview_render'),
                    'presence' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_presence', ['uuid' => $entry->getUuid()]),
                ],
                'channels' => array_map(static fn (array $channel): array => [
                    'code' => $channel['channel']->getCode(),
                    'name' => $channel['channel']->getName(),
                    'locales' => array_keys($channel['locales']),
                ], $channels),
                'locales' => $locales,
                'customerGroups' => $this->customerGroups(),
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
            $locales = [];
            if ($channel instanceof CoreChannelInterface) {
                foreach ($channel->getLocales() as $locale) {
                    $locales[(string) $locale->getCode()] = $locale;
                }

                ksort($locales);
                $default = $channel->getDefaultLocale()?->getCode();
                if (null !== $default && isset($locales[$default])) {
                    $locales = [$default => $locales[$default]] + $locales;
                }
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

    private static function query(Request $request, string $name): ?string
    {
        $value = $request->query->all()[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Back, and Esc, return to where the builder was opened from, when that's a path on this host passed as `back`, and
     * otherwise to the entry's overview page, which the builder opens from, as it does straight after the entry's created.
     * See "Into and out of the builder" in docs/spec/admin.md
     */
    private function backUrl(Request $request, EntryInterface $entry): string
    {
        $back = $request->query->get('back');
        if (is_string($back) && 1 === preg_match('#^/(?![/\\\\])#', $back)) {
            return $back;
        }

        return $this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $entry->getUuid()]);
    }
}
