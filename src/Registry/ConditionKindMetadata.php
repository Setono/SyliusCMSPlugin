<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;

/**
 * A condition kind as its #[AsConditionKind] declares it, with the fields of its parameters class
 */
final readonly class ConditionKindMetadata
{
    /**
     * @param string $label a translation key
     * @param list<string> $needs the context keys it needs
     * @param list<Reads>|null $reads what it reads, or null when it hasn't declared it
     * @param class-string|null $params its parameters class, or null when it has none
     * @param array<string, FieldMetadata> $fields the parameters editors fill in, by property name
     * @param string $matches a translation key: how the builder words a condition that matches
     * @param string $matchesNot a translation key: how the builder words a negated one
     */
    public function __construct(
        public string $code,
        public string $label,
        public array $needs,
        public ?array $reads,
        public ?string $params,
        public array $fields,
        public ConditionKindInterface $kind,
        public string $matches = 'setono_sylius_cms.builder.is_one_of',
        public string $matchesNot = 'setono_sylius_cms.builder.is_not_one_of',
    ) {
    }
}
