<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

/**
 * How the admin names channels it has the codes of: by their names, or by their codes when they have none, or when the
 * store no longer has them, as a page may stay the homepage of a channel that was deleted
 *
 * @internal
 */
final class ChannelNames
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param list<string> $codes
     *
     * @return array<string, string> each code's name, by code, in the order of the codes
     */
    public static function of(ChannelRepositoryInterface $channelRepository, array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        $names = [];
        foreach ($channelRepository->findBy(['code' => $codes]) as $channel) {
            $code = (string) $channel->getCode();
            $names[$code] = $channel->getName() ?? $code;
        }

        return array_combine($codes, array_map(static fn (string $code): string => $names[$code] ?? $code, $codes));
    }
}
