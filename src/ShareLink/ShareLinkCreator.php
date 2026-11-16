<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\ShareLink;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Preview\StartingValuesInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Setono\SyliusCMSPlugin\Security\EditorName;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class ShareLinkCreator implements ShareLinkCreatorInterface
{
    /**
     * @param FactoryInterface<ShareLinkInterface> $shareLinkFactory
     * @param ChannelRepositoryInterface<BaseChannelInterface> $channelRepository
     */
    public function __construct(
        private FactoryInterface $shareLinkFactory,
        private ShareLinkRepositoryInterface $shareLinks,
        private ChannelRepositoryInterface $channelRepository,
        private WorkingCopyProviderInterface $workingCopyProvider,
        private ContentTypeRegistryInterface $contentTypes,
        private StartingValuesInterface $startingValues,
        private TokenStorageInterface $tokenStorage,
        private ClockInterface $clock,
    ) {
    }

    public function create(EntryInterface $entry, string $channelCode, string $localeCode): ShareLinkInterface
    {
        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            throw new ShareLinkUnavailableException(sprintf('There is no channel %s', $channelCode));
        }

        if (!in_array($channelCode, $entry->getChannelCodes(), true)) {
            throw new ShareLinkUnavailableException(sprintf('The entry isn\'t in the channel %s', $channelCode));
        }

        if (!$channel->getLocales()->exists(static fn (int|string $key, LocaleInterface $locale): bool => $locale->getCode() === $localeCode)) {
            throw new ShareLinkUnavailableException(sprintf('The channel %s has no locale %s', $channelCode, $localeCode));
        }

        // The link opens the draft at the draft's URL
        $workingCopy = $this->workingCopyProvider->getWorkingCopy($entry);
        if (null === EntryPath::of($this->contentTypes->getByEntry($workingCopy), $workingCopy, $localeCode)) {
            throw new ShareLinkUnavailableException(sprintf('The entry has no URL in %s', $localeCode));
        }

        $shareLink = $this->shareLinkFactory->createNew();
        $shareLink->setEntry($entry);
        $shareLink->setChannelCode($channelCode);
        $shareLink->setLocaleCode($localeCode);
        $shareLink->setShownTo($this->startingValues->shownTo($entry));
        $shareLink->setDate($this->startingValues->date($entry));
        $shareLink->setCreatedAt($this->clock->now());
        $shareLink->setCreatedBy(EditorName::signedIn($this->tokenStorage));

        $this->shareLinks->add($shareLink);

        return $shareLink;
    }
}
