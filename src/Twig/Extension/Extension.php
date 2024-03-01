<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class Extension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sscms_asset', [Runtime::class, 'asset'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new TwigFunction('sscms_block', [Runtime::class, 'block'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new TwigFunction('sscms_carousel', [Runtime::class, 'carousel'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new TwigFunction('sscms_page', [Runtime::class, 'page'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),

            new TwigFunction('sscms_link_route', [Runtime::class, 'linkToRoute'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_resource', [Runtime::class, 'linkToResource'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_product', [Runtime::class, 'linkToProduct'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_taxon', [Runtime::class, 'linkToTaxon'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_link_page', [Runtime::class, 'linkToPage'], ['is_safe' => ['html']]),

            new TwigFunction('sscms_get_page_preview_links', [Runtime::class, 'getPagePreviewLinks'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_preview', [Runtime::class, 'preview'], ['is_safe' => ['html']]),
            new TwigFunction('sscms_max_upload_size', [Runtime::class, 'maxUploadSize']),
            new TwigFunction('sscms_readable_bytes', [Runtime::class, 'readableBytes']),

            /**
             * This function is used internally to push elements onto the element stack which is then used to display the toolbar when logged in
             *
             * @internal
             */
            new TwigFunction('sscms_push_to_element_stack', [Runtime::class, 'pushToElementStack']),
        ];
    }
}
