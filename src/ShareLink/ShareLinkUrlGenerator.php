<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\ShareLink;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewRenderContextFactory;
use Setono\SyliusCMSPlugin\Preview\SampleObjects;
use Setono\SyliusCMSPlugin\Preview\SnippetHostPages;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URL of a share link: its entry's URL in its channel and locale, on the channel's hostname, with its token in the
 * _cms_share query parameter. It's the URL of the entry's draft, which is what the link shows, so it follows the draft's
 * slug. A snippet's is the URL of the host page of its placement, the page of its sample object, or a plain page. The
 * router's context is put back afterwards. See "Share links" in docs/spec/publishing.md
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
        private SnippetHostPages $snippetHostPages,
        private SampleObjects $samples,
        private PreviewRenderContextFactory $renderContextFactory,
    ) {
    }

    /**
     * Null when the entry's draft no longer has a URL in the link's locale
     */
    public function generate(ShareLinkInterface $shareLink): ?string
    {
        $channel = $this->channelRepository->findOneByCode($shareLink->getChannelCode());
        $workingCopy = $this->workingCopyProvider->getWorkingCopy($shareLink->getEntry());

        $original = $this->urlGenerator->getContext();
        $context = clone $original;
        $context->setHost($channel?->getHostname() ?? $original->getHost());
        $this->urlGenerator->setContext($context);

        try {
            return $workingCopy instanceof SnippetInterface ? $this->hostPage($shareLink, $workingCopy, $channel) : $this->entryPage($shareLink, $workingCopy);
        } finally {
            $this->urlGenerator->setContext($original);
        }
    }

    private function entryPage(ShareLinkInterface $shareLink, EntryInterface $workingCopy): ?string
    {
        $path = EntryPath::of($this->contentTypes->getByEntry($workingCopy), $workingCopy, $shareLink->getLocaleCode());
        if (null === $path) {
            return null;
        }

        return $this->urlGenerator->generate('setono_sylius_cms_shop_entry', [
            '_locale' => $shareLink->getLocaleCode(),
            'path' => $path,
            PreviewContext::SHARE_QUERY_PARAMETER => $shareLink->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The page of the sample object the link shows the snippet's placement on, or a plain page
     */
    private function hostPage(ShareLinkInterface $shareLink, SnippetInterface $workingCopy, ?ChannelInterface $channel): ?string
    {
        if (!$channel instanceof CoreChannelInterface) {
            return null;
        }

        $sample = $shareLink->getSample();
        $preview = new Preview($workingCopy->getUuid(), $shareLink->getChannelCode(), $shareLink->getLocaleCode(), null, $shareLink->getShownTo(), $shareLink->getDate(), true);

        return $this->snippetHostPages->url(
            $shareLink->getPlacement(),
            null === $sample ? null : $this->samples->load($sample, $this->renderContextFactory->create($preview, $channel)),
            $channel,
            $shareLink->getLocaleCode(),
            [PreviewContext::SHARE_QUERY_PARAMETER => $shareLink->getToken()],
        );
    }
}
