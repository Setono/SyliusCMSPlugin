<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Media\MediaLocales;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What the media library app needs to reach the library, on its own screen and as the builder's media picker: its
 * endpoints, and the store's locales, which media items have alt text in
 *
 * @internal
 */
final readonly class MediaLibraryConfig
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private MediaLocales $locales,
    ) {
    }

    /**
     * @param string $displayLocale the locale the locales are named in, the admin user's
     *
     * @return array{endpoints: array{media: string, folders: string}, locales: list<array{code: string, name: string}>}
     */
    public function of(string $displayLocale): array
    {
        return [
            'endpoints' => [
                // GET lists, POST uploads, and /{uuid} saves one with PATCH
                'media' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_media_index'),
                // GET lists, POST creates, and /{uuid} renames or moves one with PATCH
                'folders' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_media_folder_index'),
            ],
            'locales' => $this->locales->all($displayLocale),
        ];
    }
}
