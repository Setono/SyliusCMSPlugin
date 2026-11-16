<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Matches when the shopper's customer group value is one of the groups: Sylius gives a customer at most one group, so
 * it checks one value, the group's code, "@guest" or "@no_group". It reads the value from the render context, which
 * the preview's Shown to sets. A deleted group's code matches nobody. See "Condition kinds" and "Deleted customer
 * groups" in docs/spec/targeting.md
 */
#[AsConditionKind(code: 'customer_group', label: 'setono_sylius_cms.condition_kind.customer_group', reads: [Reads::Customer], params: CustomerGroupParams::class)]
final class CustomerGroupKind implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof CustomerGroupParams) {
            throw new \InvalidArgumentException(sprintf('The customer group condition takes %s, got %s', CustomerGroupParams::class, $params::class));
        }

        return in_array($context->customerGroup, $params->groups, true);
    }
}
