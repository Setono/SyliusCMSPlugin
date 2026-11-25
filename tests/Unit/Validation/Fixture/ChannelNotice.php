<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;

/**
 * A project's block type with a field that picks one of the store's channels
 */
#[BlockType(code: 'channel_notice', label: 'app.channel_notice', template: 'channel_notice.html.twig')]
final class ChannelNotice
{
    #[Choice(label: 'Channel', choicesProvider: ChannelChoicesProvider::class)]
    public ?string $channel = null;
}
