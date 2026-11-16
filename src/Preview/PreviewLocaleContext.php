<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;

/**
 * Forces the preview's locale in Sylius's locale context, ahead of the admin user's own locale. The builder renders
 * blocks through an admin URL (preview_render), and they render in the preview's locale there, as on the shop page it
 * previews. Sylius sets the request's locale from this context
 */
final readonly class PreviewLocaleContext implements LocaleContextInterface
{
    public function __construct(private PreviewContext $previewContext)
    {
    }

    public function getLocaleCode(): string
    {
        return $this->previewContext->getPreview()->localeCode ?? throw new LocaleNotFoundException('The request previews no locale');
    }
}
