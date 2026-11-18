<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's page-scope condition kind that always throws, as one asking a stock service that's down would
 */
#[AsConditionKind(code: 'in_stock', label: 'In stock', reads: [Reads::HostPage])]
final class InStock implements ConditionKindInterface
{
    public const MESSAGE = 'The stock service is down';

    public function matches(object $params, RenderContext $context): bool
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
