<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;

/**
 * Channels whose names are translated, with a numeric code, which PHP keeps as an int key. In Danish a channel is
 * missing, so a test can tell the locale a handler asks in
 */
final class ChannelsByLocale implements ChoicesProviderInterface
{
    public function getChoices(string $localeCode): array
    {
        return match ($localeCode) {
            'da_DK' => ['WEB' => 'Webbutik', '42' => 'Butik 42'],
            default => ['WEB' => 'Web store', '42' => 'Store 42', 'B2B' => 'Wholesale'],
        };
    }
}
