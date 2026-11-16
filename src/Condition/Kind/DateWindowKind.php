<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Condition\TimeDependentConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Matches inside the window: from its start, inclusive, until its end, exclusive, so a window that ends at
 * 2026-12-01T00:00:00Z shows until the last second of 30 November. Either end is optional. It compares the time from
 * the render context, which the preview's Date sets. See "Condition kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: 'date_window', label: 'setono_sylius_cms.condition_kind.date_window', reads: [Reads::Time], params: DateWindowParams::class)]
final class DateWindowKind implements TimeDependentConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        $params = self::params($params);

        return (null === $params->from || $params->from <= $context->now) && (null === $params->until || $context->now < $params->until);
    }

    /**
     * The window's next end after $now: its start while it's ahead, then its end
     */
    public function nextChange(object $params, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $params = self::params($params);

        foreach ([$params->from, $params->until] as $end) {
            if (null !== $end && $now < $end) {
                return $end;
            }
        }

        return null;
    }

    private static function params(object $params): DateWindowParams
    {
        if (!$params instanceof DateWindowParams) {
            throw new \InvalidArgumentException(sprintf('The date window condition takes %s, got %s', DateWindowParams::class, $params::class));
        }

        return $params;
    }
}
