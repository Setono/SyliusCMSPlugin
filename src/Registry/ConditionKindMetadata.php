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
     */
    public function __construct(
        public string $code,
        public string $label,
        public array $needs,
        public ?array $reads,
        public ?string $params,
        public array $fields,
        public ConditionKindInterface $kind,
    ) {
    }
}
