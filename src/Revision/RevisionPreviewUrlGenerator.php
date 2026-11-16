<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URL of a revision's read-only preview: its page in the shop, at the URL the revision gives it, with a preview
 * token that shows the revision's content. It's on the admin's own host, as the builder's preview is, so it works
 * whatever hostnames the channels have. See "Revisions" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class RevisionPreviewUrlGenerator
{
    /**
     * @param ChannelRepositoryInterface<BaseChannelInterface> $channelRepository
     */
    public function __construct(
        private RevisionReader $reader,
        private ContentTypeRegistryInterface $contentTypes,
        private ChannelRepositoryInterface $channelRepository,
        private PreviewTokenSigner $signer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param string|null $channelCode one of the entry's channels, or null for the first of them by code
     * @param string|null $localeCode one of the channel's locales, or null for the first the revision has a URL in: the
     *                                channel's default locale, then its others by code
     *
     * @throws RevisionPreviewUnavailableException when the revision has no page to preview there
     * @throws InvalidEntryFormatException when the revision's content isn't its entry in the entry format
     * @throws \InvalidArgumentException when the revision isn't stored, so a token can't name it
     */
    public function generate(RevisionInterface $revision, ?string $channelCode = null, ?string $localeCode = null): string
    {
        $revisionId = $revision->getId() ?? throw new \InvalidArgumentException('Only a stored revision can be previewed');
        $entry = $this->reader->entry($revision);
        $contentType = $this->contentTypes->getByEntry($entry);

        $channelCode ??= $entry->getChannelCodes()[0] ?? throw new RevisionPreviewUnavailableException('The entry isn\'t in any channel');
        if (!in_array($channelCode, $entry->getChannelCodes(), true)) {
            throw new RevisionPreviewUnavailableException(sprintf('The entry isn\'t in the channel %s', $channelCode));
        }

        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            throw new RevisionPreviewUnavailableException(sprintf('There is no channel %s', $channelCode));
        }

        $channelLocaleCodes = ChannelLocales::of($channel);
        if (null !== $localeCode && !in_array($localeCode, $channelLocaleCodes, true)) {
            throw new RevisionPreviewUnavailableException(sprintf('The channel %s has no locale %s', $channelCode, $localeCode));
        }

        foreach (null === $localeCode ? $channelLocaleCodes : [$localeCode] as $candidate) {
            $path = EntryPath::of($contentType, $entry, $candidate);
            if (null !== $path) {
                return $this->urlGenerator->generate('setono_sylius_cms_shop_entry', [
                    '_locale' => $candidate,
                    'path' => $path,
                    PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview($entry->getUuid(), $channelCode, $candidate, $revisionId)),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        throw new RevisionPreviewUnavailableException(null === $localeCode ? sprintf('The revision has no URL in the channel %s', $channelCode) : sprintf('The revision has no URL in %s', $localeCode));
    }
}
