<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUrlGenerator;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;

/**
 * The share links of an entry, which the Share links section of its overview page lists through
 * cms_admin_entry_share_links(), and the channels and locales its Share draft offers to share the draft in, through
 * cms_admin_share_link_targets(). See "Entry overview page" in docs/spec/admin.md
 */
final readonly class EntryShareLinks
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private ShareLinkRepositoryInterface $shareLinks,
        private ShareLinkUrlGenerator $urlGenerator,
        private ChannelRepositoryInterface $channelRepository,
        private WorkingCopyProviderInterface $workingCopyProvider,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * @return list<EntryShareLink> newest first
     */
    public function of(EntryInterface $entry): array
    {
        return array_map(fn (ShareLinkInterface $shareLink): EntryShareLink => new EntryShareLink(
            $shareLink,
            $this->urlGenerator->generate($shareLink),
            self::nameOf($this->channelRepository->findOneByCode($shareLink->getChannelCode()), $shareLink->getChannelCode()),
        ), $this->shareLinks->findByEntry($entry));
    }

    /**
     * The channels and locales the entry's draft has a URL in: the entry's channels by code, each with its default
     * locale first and then its other locales by code
     *
     * @return list<ShareLinkTarget>
     */
    public function targets(EntryInterface $entry): array
    {
        $workingCopy = $this->workingCopyProvider->getWorkingCopy($entry);
        $contentType = $this->contentTypes->getByEntry($workingCopy);

        $channelCodes = $entry->getChannelCodes();
        sort($channelCodes);

        $targets = [];
        foreach ($channelCodes as $channelCode) {
            $channel = $this->channelRepository->findOneByCode($channelCode);
            if (!$channel instanceof CoreChannelInterface) {
                continue;
            }

            foreach (self::localeCodesOf($channel) as $localeCode) {
                if (null !== EntryPath::of($contentType, $workingCopy, $localeCode)) {
                    $targets[] = new ShareLinkTarget($channelCode, self::nameOf($channel, $channelCode), $localeCode);
                }
            }
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    private static function localeCodesOf(CoreChannelInterface $channel): array
    {
        $codes = [];
        foreach ($channel->getLocales() as $locale) {
            $codes[] = (string) $locale->getCode();
        }
        sort($codes);

        $default = $channel->getDefaultLocale()?->getCode();

        return null === $default ? $codes : array_values(array_unique([$default, ...$codes]));
    }

    private static function nameOf(?ChannelInterface $channel, string $code): string
    {
        $name = $channel?->getName();

        return null === $name || '' === $name ? $code : $name;
    }
}
