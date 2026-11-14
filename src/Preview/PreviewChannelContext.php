<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

/**
 * Forces the preview's channel in Sylius's channel context, ahead of the hostname. The builder loads the preview from
 * the admin's own host, so it works when channels have their own hostnames (ADR 0008)
 */
final readonly class PreviewChannelContext implements ChannelContextInterface
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private PreviewContext $previewContext,
        private ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public function getChannel(): ChannelInterface
    {
        $preview = $this->previewContext->getPreview();
        $channel = null === $preview ? null : $this->channelRepository->findOneByCode($preview->channelCode);

        return $channel ?? throw new ChannelNotFoundException('The request previews no channel');
    }
}
