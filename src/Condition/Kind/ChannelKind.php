<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Matches when the shop's channel is one of the channels. See "Condition kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.channel', reads: [Reads::Channel], params: ChannelParams::class)]
final class ChannelKind implements ConditionKindInterface
{
    public const CODE = 'channel';

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof ChannelParams) {
            throw new \InvalidArgumentException(sprintf('The channel condition takes %s, got %s', ChannelParams::class, $params::class));
        }

        return in_array($context->channel->getCode(), $params->channels, true);
    }
}
