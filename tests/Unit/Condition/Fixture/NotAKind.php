<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;

/**
 * A condition kind that doesn't implement ConditionKindInterface
 */
#[AsConditionKind(code: 'not_a_kind', label: 'Not a kind')]
final class NotAKind
{
}
