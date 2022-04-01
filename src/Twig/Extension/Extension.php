<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Twig\TokenParser\SectionTokenParser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class Extension extends AbstractExtension
{
    public function getTokenParsers(): array
    {
        return [
            new SectionTokenParser(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sscms_block', [Runtime::class, 'block'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_view', [Runtime::class, 'view'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_carousel', [Runtime::class, 'carousel'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_route', [Runtime::class, 'linkToRoute'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_resource', [Runtime::class, 'linkToResource'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_product', [Runtime::class, 'linkToProduct'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_taxon', [Runtime::class, 'linkToTaxon'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_page', [Runtime::class, 'linkToPage'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_get_page_preview_links', [Runtime::class, 'getPagePreviewLinks'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_preview', [Runtime::class, 'preview'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_max_upload_size', [Runtime::class, 'maxUploadSize']),
            new TwigFunction('sscms_readable_bytes', [Runtime::class, 'readableBytes']),
        ];
    }
}
