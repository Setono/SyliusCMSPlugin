<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Builder\UiStrings;
use Setono\SyliusCMSPlugin\Media\MediaTypes;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The media library's screen in the Content hub: the hub's page around the library app, which is part of the builder
 * app, so the builder's media picker opens the same library. The app gets its config here: the endpoints, the CSRF
 * token, the store's locales for the alt text, the upload limit and types, and its UI strings.
 * See "Media library" in docs/spec/admin.md
 */
final readonly class MediaLibraryAction
{
    private const STRINGS_PREFIX = 'setono_sylius_cms.builder.media.';

    /**
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private UrlGeneratorInterface $urlGenerator,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private RepositoryInterface $localeRepository,
        private UploadLimit $uploadLimit,
        private MediaTypes $mediaTypes,
        private UiStrings $uiStrings,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/media/index.html.twig', [
            'config' => [
                'csrfToken' => $this->csrfToken->value(),
                'endpoints' => [
                    // GET lists, POST uploads, and /{uuid} saves one with PATCH
                    'media' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_media_index'),
                    // GET lists, POST creates, and /{uuid} renames or moves one with PATCH
                    'folders' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_media_folder_index'),
                ],
                'locales' => $this->locales($request->getLocale()),
                'media' => ['maxUploadSize' => $this->uploadLimit->bytes(), 'types' => $this->mediaTypes->all()],
                'strings' => $this->uiStrings->withPrefix(self::STRINGS_PREFIX),
            ],
        ]));
    }

    /**
     * The store's locales, which media items have alt text in, the default first, named in the admin user's locale
     *
     * @return list<array{code: string, name: string}>
     */
    private function locales(string $displayLocale): array
    {
        $names = [];
        foreach ($this->localeRepository->findAll() as $locale) {
            $names[(string) $locale->getCode()] = $locale->getName($displayLocale) ?? (string) $locale->getCode();
        }

        $codes = $this->translationLocaleProvider->getDefinedLocalesCodes();
        $default = $this->translationLocaleProvider->getDefaultLocaleCode();
        usort($codes, static fn (string $a, string $b): int => [$a !== $default, $a] <=> [$b !== $default, $b]);

        return array_map(static fn (string $code): array => ['code' => $code, 'name' => $names[$code] ?? $code], $codes);
    }
}
