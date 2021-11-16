<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Page;

use Stringable;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;

final class PreviewLink implements Stringable
{
    public ChannelInterface $channel;

    public LocaleInterface $locale;

    public string $url;

    public function __construct(ChannelInterface $channel, LocaleInterface $locale, string $url)
    {
        $this->channel = $channel;
        $this->locale = $locale;
        $this->url = $url;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
