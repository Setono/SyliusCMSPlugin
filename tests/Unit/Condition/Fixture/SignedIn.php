<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind without parameters, which declares nothing about what it reads
 */
#[AsConditionKind(code: 'signed_in', label: 'Signed in')]
final class SignedIn implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof NoParams) {
            throw new \InvalidArgumentException('A kind without parameters gets NoParams');
        }

        return null !== $context->customer;
    }
}
