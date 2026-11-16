<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;

final class ChannelParams
{
    /** @var list<string> channel codes */
    #[Choice(label: 'setono_sylius_cms.field.channel_condition.channels', multiple: true, choicesProvider: ChannelChoicesProvider::class)]
    public array $channels = [];
}
