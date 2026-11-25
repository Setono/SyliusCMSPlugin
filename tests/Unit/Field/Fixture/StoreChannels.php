<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\RecordChoicesProviderInterface;

/**
 * The store's channels by code, as the shipped channel choices are: a code it no longer gives names a deleted channel
 */
final class StoreChannels implements RecordChoicesProviderInterface
{
    public function getChoices(string $localeCode): array
    {
        return ['WEB' => 'Web store', 'B2B' => 'Wholesale'];
    }
}
