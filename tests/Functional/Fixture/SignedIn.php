<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's condition kind without parameters, which matches a signed-in shopper. GreetingKernel registers it, so a
 * test can save a condition of it and then run the code without it, as after a deploy that removed it
 */
#[AsConditionKind(code: 'app_signed_in', label: 'app.cms.condition_kind.signed_in', reads: [Reads::Customer])]
final class SignedIn implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return null !== $context->customer;
    }
}
