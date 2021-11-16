<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Page;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;

final class PreviewLink
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
}
