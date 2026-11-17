<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Builder\RegistryExporter;
use Setono\SyliusCMSPlugin\Builder\UiStrings;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_admin_page_scope_editor(): what the page scope editor of the Placements section of a snippet's overview page
 * shares between its placements. The editor is part of the prebuilt builder app, and renders the condition kinds' fields
 * as the builder's visibility conditions do. See "Placements" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class PageScopeEditorRuntime implements RuntimeExtensionInterface
{
    private const STRINGS_PREFIX = 'setono_sylius_cms.builder.';

    public function __construct(
        private RegistryExporter $registry,
        private UiStrings $uiStrings,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The condition kinds a page scope can hold, with their parameters' field schemas, the context keys they need, the
     * search pickers search with, the admin user's locale and the UI strings
     *
     * @return array<string, mixed>
     */
    public function config(string $localeCode): array
    {
        return [
            ...$this->registry->exportForPageScopes(),
            'endpoints' => [
                // What pickers search, with __kind__ for the kind, such as product
                'search' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_search', ['kind' => '__kind__']),
            ],
            'locale' => $localeCode,
            'strings' => $this->uiStrings->withPrefix(self::STRINGS_PREFIX),
        ];
    }
}
