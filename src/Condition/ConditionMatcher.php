<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Decides whether visibility conditions in the stored condition format match the shopper and the page in a render
 * context. A block's conditions combine with AND, and the values within one condition with OR, which its kind decides.
 * A condition's `negate` gives "is not one of": the plugin negates, so kinds never do. A condition of a kind the code
 * no longer has never matches, so its block stays hidden rather than showing to shoppers it was meant to be hidden from.
 * A parameter that refers to a record, such as a project's kind's product, is hydrated from the records loaded before
 * rendering, with the rest of the tree's references, which references() lists for that pass.
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
     * @param ReferenceMap $references the records the tree's references resolve to, its conditions' included
     */
    public function matchesAll(array $conditions, RenderContext $context, ReferenceMap $references = new ReferenceMap()): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->matches($condition, $context, $references)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $condition
     * @param ReferenceMap $references the records the tree's references resolve to, its conditions' included
     */
    public function matches(array $condition, RenderContext $context, ReferenceMap $references = new ReferenceMap()): bool
    {
        $kind = $this->kindOf($condition);
        if (null === $kind) {
            return false;
        }

        $params = $this->paramsHydrator->hydrate($kind->params, $kind->fields, self::paramsOf($condition), new HydrationContext($context, $references));

        return $kind->kind->matches($params, $context) !== (true === ($condition['negate'] ?? false));
    }

    /**
     * The references the conditions' parameter values hold, in order, leaving out a condition of a kind the code no
     * longer has, which never matches
     *
     * @param list<array<string, mixed>> $conditions
     *
     * @return list<Reference>
     */
    public function references(array $conditions): array
    {
        $references = [];
        foreach ($conditions as $condition) {
            $kind = $this->kindOf($condition);
            if (null !== $kind) {
                $references = [...$references, ...$this->paramsHydrator->references($kind->fields, self::paramsOf($condition))];
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function kindOf(array $condition): ?ConditionKindMetadata
    {
        $code = $condition['kind'] ?? null;

        return is_string($code) && $this->kinds->has($code) ? $this->kinds->get($code) : null;
    }

    /**
     * The stored parameter values. Values that aren't a map, such as an empty JSON object as CanonicalJson::decode()
     * reads it, are none
     *
     * @param array<string, mixed> $condition
     *
     * @return array<array-key, mixed>
     */
    private static function paramsOf(array $condition): array
    {
        $values = $condition['params'] ?? null;

        return is_array($values) ? $values : [];
    }
}
