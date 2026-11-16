<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Resource\Factory\TranslatableFactoryInterface;

/**
 * Decorates every content type's resource factory, so a new entry starts in every channel however it's created: in the
 * admin, in a fixture, an import or a project's own code. See "Channels" in docs/spec/localization.md
 *
 * @template T of EntryInterface
 * @implements TranslatableFactoryInterface<T>
 */
final readonly class EntryFactory implements TranslatableFactoryInterface
{
    /**
     * @param FactoryInterface<T> $decorated
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private FactoryInterface $decorated,
        private ChannelRepositoryInterface $channelRepository,
        private ChannelsAndAudienceModifierInterface $channelsAndAudienceModifier,
    ) {
    }

    /**
     * @return T
     */
    public function createNew(): EntryInterface
    {
        $entry = $this->decorated->createNew();
        if (!$entry instanceof EntryInterface) {
            throw new \LogicException(sprintf('A content type\'s factory must create %s, not %s', EntryInterface::class, get_debug_type($entry)));
        }

        $codes = array_map(static fn (ChannelInterface $channel): ?string => $channel->getCode(), $this->channelRepository->findAll());
        $this->channelsAndAudienceModifier->setChannels($entry, array_values(array_filter($codes, static fn (?string $code): bool => null !== $code)));

        return $entry;
    }
}
