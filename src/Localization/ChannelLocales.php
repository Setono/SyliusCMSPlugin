<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The locales of the store's channels, in the order the admin offers them: a channel's default locale first, and then
 * its others by code. The admin offers a translation for each locale of the entry's channels. See "Which locales an
 * entry has" in docs/spec/localization.md
 */
final class ChannelLocales implements ResetInterface
{
    /** @var array<string, list<string>>|null by channel code, read once */
    private ?array $byChannel = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    /**
     * The channel's locale codes, its default locale first and then the others by code. A channel that isn't a shop's
     * has none
     *
     * @return list<string>
     */
    public static function of(ChannelInterface $channel): array
    {
        if (!$channel instanceof CoreChannelInterface) {
            return [];
        }

        $codes = [];
        foreach ($channel->getLocales() as $locale) {
            $code = $locale->getCode();
            if (null !== $code) {
                $codes[] = $code;
            }
        }

        $default = $channel->getDefaultLocale()?->getCode();
        usort($codes, static fn (string $a, string $b): int => [$a !== $default, $a] <=> [$b !== $default, $b]);

        return array_values(array_unique($codes));
    }

    /**
     * The locales of these channels, each once, channel by channel in the order the store lists its channels. A channel
     * the store doesn't have has none
     *
     * @param list<string> $channelCodes
     *
     * @return list<string>
     */
    public function ofChannels(array $channelCodes): array
    {
        if (null === $this->byChannel) {
            $this->byChannel = [];
            foreach ($this->channelRepository->findBy([], ['id' => 'ASC']) as $channel) {
                $code = $channel->getCode();
                if (null !== $code) {
                    $this->byChannel[$code] = self::of($channel);
                }
            }
        }

        $locales = [];
        foreach ($this->byChannel as $channelCode => $localeCodes) {
            if (in_array($channelCode, $channelCodes, true)) {
                $locales = [...$locales, ...$localeCodes];
            }
        }

        return array_values(array_unique($locales));
    }

    public function reset(): void
    {
        $this->byChannel = null;
    }
}
