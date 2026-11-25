<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot\Fixture;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;

final class SoldInParams
{
    /** @var list<string> channel codes */
    #[Choice(label: 'app.sold_in.channels', multiple: true, choicesProvider: ChannelChoicesProvider::class)]
    public array $channels = [];
}
