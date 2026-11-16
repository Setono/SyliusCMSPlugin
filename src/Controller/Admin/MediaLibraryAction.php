<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Builder\UiStrings;
use Setono\SyliusCMSPlugin\Media\MediaLocales;
use Setono\SyliusCMSPlugin\Media\MediaTypes;
use Setono\SyliusCMSPlugin\Media\UploadLimit;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
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

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private UrlGeneratorInterface $urlGenerator,
        private MediaLocales $locales,
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
                'locales' => $this->locales->all($request->getLocale()),
                'media' => ['maxUploadSize' => $this->uploadLimit->bytes(), 'types' => $this->mediaTypes->all()],
                'strings' => $this->uiStrings->withPrefix(self::STRINGS_PREFIX),
            ],
        ]));
    }
}
