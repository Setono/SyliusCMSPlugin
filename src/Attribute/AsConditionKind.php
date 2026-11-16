<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

use Setono\SyliusCMSPlugin\Condition\Reads;

/**
 * Registers a condition kind: a service implementing Setono\SyliusCMSPlugin\Condition\ConditionKindInterface.
 * See "Condition kinds" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsConditionKind
{
    /**
     * @param string $code stored in each condition's "kind"; matches ^[a-z][a-z0-9_]*$ and never changes
     * @param string $label a translation key
     * @param list<string> $needs the context keys it needs, such as "product"
     * @param list<Reads>|null $reads what it reads. Null, the default, means unknown, which a later cache treats as
     *                               uncacheable, and [] claims it reads nothing. A kind that reads Reads::Time
     *                               implements TimeDependentConditionKindInterface
     * @param class-string|null $params its parameters class, whose typed properties with field attributes are the
     *                                  parameters editors fill in, or null for none, when it gets a NoParams
     * @param string $matches a translation key: how the builder words a condition that matches, "is one of" unless the
     *                        kind matches otherwise, such as a date window's "is inside"
     * @param string $matchesNot a translation key: how the builder words a negated one, "is not one of" unless the
     *                           kind matches otherwise, such as "is outside"
     */
    public function __construct(
        public string $code,
        public string $label,
        public array $needs = [],
        public ?array $reads = null,
        public ?string $params = null,
        public string $matches = 'setono_sylius_cms.builder.is_one_of',
        public string $matchesNot = 'setono_sylius_cms.builder.is_not_one_of',
    ) {
    }
}
