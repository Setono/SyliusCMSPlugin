<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Builder\MediaLibraryConfig;
use Setono\SyliusCMSPlugin\Builder\UiStrings;
use Setono\SyliusCMSPlugin\Media\MediaLimits;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private MediaLibraryConfig $libraryConfig,
        private MediaLimits $limits,
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
                ...$this->libraryConfig->of($request->getLocale()),
                'media' => $this->limits->toArray(),
                'strings' => $this->uiStrings->withPrefix(self::STRINGS_PREFIX),
            ],
        ]));
    }
}
