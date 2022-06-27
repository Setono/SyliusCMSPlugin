<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ImagePreviewer implements PreviewerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function preview(AssetInterface $asset): Preview
    {
        return new Preview(sprintf(
            '<img src="%s" alt="%s" style="width: 100%%">',
            $this->urlGenerator->generate('setono_sylius_cms_view_asset', ['id' => $asset->getId()]),
            (string) $asset->getName()
        ));
    }

    public function supports(AssetInterface $asset): bool
    {
        $mimeType = $asset->getMimeType();
        if (null === $mimeType) {
            return false;
        }

        return strpos($mimeType, 'image') !== false;
    }
}
