<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Decides whether visibility conditions in the stored condition format match the shopper and the page in a render
 * context. A block's conditions combine with AND, and the values within one condition with OR, which its kind decides.
 * A condition's `negate` gives "is not one of": the plugin negates, so kinds never do. A condition of a kind the code
 * no longer has never matches, so its block stays hidden rather than showing to shoppers it was meant to be hidden from.
 * See "Visibility conditions" in docs/spec/targeting.md. Projects add condition kinds rather than change how they combine
 *
 * @internal
 */
final readonly class ConditionMatcher
{
    public function __construct(
        private ConditionKindRegistryInterface $kinds,
        private ParamsHydrator $paramsHydrator,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $conditions
     */
    public function matchesAll(array $conditions, RenderContext $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->matches($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $condition
     */
    public function matches(array $condition, RenderContext $context): bool
    {
        $code = $condition['kind'] ?? null;
        if (!is_string($code) || !$this->kinds->has($code)) {
            return false;
        }

        $kind = $this->kinds->get($code);
        // Parameter values that aren't a map, such as an empty JSON object as CanonicalJson::decode() reads it, are none
        $values = $condition['params'] ?? null;
        $params = $this->paramsHydrator->hydrate($kind->params, $kind->fields, is_array($values) ? $values : [], new HydrationContext($context));

        return $kind->kind->matches($params, $context) !== (true === ($condition['negate'] ?? false));
    }
}
