<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Targeting\ChannelNames;
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

        foreach (ChannelNames::of($this->channels, $entry->getHomepageChannelCodesOutsideChannels()) as $name) {
            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.homepage_not_in_channel', ['%channel%' => $name]),
                Fix::inBuilder($entry),
            );
        }
    }
}
