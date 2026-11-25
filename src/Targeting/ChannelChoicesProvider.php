<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Field\RecordChoicesProviderInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The store's channels, by code, with their names, for a Choice field such as the channel condition's channels.
 * They're read once per request
 */
final class ChannelChoicesProvider implements RecordChoicesProviderInterface, ResetInterface
{
    /** @var array<array-key, string>|null */
    private ?array $choices = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    /**
     * A channel's name is the same in every locale
     */
    public function getChoices(string $localeCode): array
    {
        if (null !== $this->choices) {
            return $this->choices;
        }

        $choices = [];
        foreach ($this->channelRepository->findBy([], ['name' => 'ASC']) as $channel) {
            $code = $channel->getCode();
            if (null !== $code) {
                $choices[$code] = $channel->getName() ?? $code;
            }
        }

        return $this->choices = $choices;
    }

    public function reset(): void
    {
        $this->choices = null;
    }
}
