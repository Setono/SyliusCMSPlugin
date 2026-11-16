<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A kind of visibility condition, such as "customer group". A kind is a service carrying #[AsConditionKind], which
 * autoconfiguration registers. Kinds work in visibility conditions on blocks and in page scopes, never on entries.
 * See "Condition kinds" in docs/spec/extending.md
 */
interface ConditionKindInterface
{
    /**
     * Whether the shopper or the page is one of the condition's values. This answers "is one of": the plugin provides
     * "is not one of" by negating it, so a kind never does
     *
     * @param object $params the kind's parameters class, hydrated from the condition's parameter values, or an instance
     *                       of Setono\SyliusCMSPlugin\Attribute\NoParams for a kind without parameters
     */
    public function matches(object $params, RenderContext $context): bool;
}
