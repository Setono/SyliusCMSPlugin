<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The entry's URL, /{locale}/{prefix}/{slug}, on the host of the request, which is the channel's, or only its path
 */
final readonly class CanonicalUrlGenerator implements CanonicalUrlGeneratorInterface
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function generate(EntryInterface $entry, ChannelInterface $channel, string $localeCode, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): ?string
    {
        $path = EntryPath::of($this->contentTypes->getByEntry($entry), $entry, $localeCode);
        if (null === $path) {
            return null;
        }

        return $this->urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => $localeCode, 'path' => $path], $referenceType);
    }
}
