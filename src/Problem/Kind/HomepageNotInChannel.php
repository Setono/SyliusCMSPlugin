<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A page that's the homepage of a channel it isn't in, a warning for each such channel: shoppers there never see it, so
 * the store's own homepage shows. A page only becomes the homepage of a channel it's in, so it has left the channel
 * since. It's fixed in the builder's settings, by putting the page back in the channel or taking the channel out of
 * "Homepage of". Both are on-save settings, whose save counts the page again. setono:cms:lint doesn't report it.
 * See "Homepage" in docs/spec/urls-and-seo.md and "Problems" in docs/spec/admin.md
 */
final readonly class HomepageNotInChannel implements ProblemKindInterface
{
    public const CODE = 'homepage_not_in_channel';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channels
     */
    public function __construct(private ChannelRepositoryInterface $channels)
    {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof PageInterface) {
            return;
        }

        $outside = array_values(array_diff($entry->getHomepageChannelCodes(), $entry->getChannelCodes()));
        if ([] === $outside) {
            return;
        }

        // A deleted channel is named by its code
        $names = [];
        foreach ($this->channels->findBy(['code' => $outside]) as $channel) {
            $names[(string) $channel->getCode()] = $channel->getName() ?? (string) $channel->getCode();
        }

        foreach ($outside as $code) {
            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.homepage_not_in_channel', ['%channel%' => $names[$code] ?? $code]),
                Fix::inBuilder($entry),
            );
        }
    }
}
