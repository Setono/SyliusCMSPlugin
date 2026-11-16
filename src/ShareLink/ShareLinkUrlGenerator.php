<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\ShareLink;

use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URL of a share link: its entry's URL in its channel and locale, on the channel's hostname, with its token in the
 * _cms_share query parameter. It's the URL of the entry's draft, which is what the link shows, so it follows the draft's
 * slug. The router's context is put back afterwards. See "Share links" in docs/spec/publishing.md
 */
final readonly class ShareLinkUrlGenerator implements ShareLinkUrlGeneratorInterface
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private WorkingCopyProviderInterface $workingCopyProvider,
        private ContentTypeRegistryInterface $contentTypes,
        private ChannelRepositoryInterface $channelRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Null when the entry's draft no longer has a URL in the link's locale
     */
    public function generate(ShareLinkInterface $shareLink): ?string
    {
        $workingCopy = $this->workingCopyProvider->getWorkingCopy($shareLink->getEntry());
        $path = EntryPath::of($this->contentTypes->getByEntry($workingCopy), $workingCopy, $shareLink->getLocaleCode());
        if (null === $path) {
            return null;
        }

        $original = $this->urlGenerator->getContext();
        $context = clone $original;
        $context->setHost($this->channelRepository->findOneByCode($shareLink->getChannelCode())?->getHostname() ?? $original->getHost());
        $this->urlGenerator->setContext($context);

        try {
            return $this->urlGenerator->generate('setono_sylius_cms_shop_entry', [
                '_locale' => $shareLink->getLocaleCode(),
                'path' => $path,
                PreviewContext::SHARE_QUERY_PARAMETER => $shareLink->getToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            $this->urlGenerator->setContext($original);
        }
    }
}
