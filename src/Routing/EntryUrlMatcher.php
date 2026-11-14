<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The condition of the setono_sylius_cms_shop_entry route, and what its controller asks for the match.
 * It looks the URL up by the channel from Sylius's channel context, the locale from the URL and the path,
 * memoised within the request so the condition and the controller share one lookup. See "Routing" in docs/spec/urls-and-seo.md
 */
final class EntryUrlMatcher implements ResetInterface
{
    /** @var array<string, EntryUrlMatch|null> */
    private array $matches = [];

    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly EntryUrlLookupInterface $entryUrlLookup,
    ) {
    }

    /**
     * Called by the route condition with the matched route parameters. It runs before the firewall,
     * so it only checks that the URL exists in the channel and locale
     *
     * @param array<array-key, mixed> $params
     */
    public function matches(array $params): bool
    {
        $localeCode = $params['_locale'] ?? null;
        $path = $params['path'] ?? null;

        return is_string($localeCode) && is_string($path) && null !== $this->match($localeCode, $path);
    }

    public function match(string $localeCode, string $path): ?EntryUrlMatch
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        if (!$channel instanceof ChannelInterface) {
            return null;
        }

        $key = implode("\0", [$channel->getCode(), $localeCode, $path]);
        if (!array_key_exists($key, $this->matches)) {
            $this->matches[$key] = $this->entryUrlLookup->lookup($channel, $localeCode, $path);
        }

        return $this->matches[$key];
    }

    public function reset(): void
    {
        $this->matches = [];
    }
}
