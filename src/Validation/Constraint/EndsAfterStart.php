<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * One Date or Date and time field of a class ends a span another one starts, so when both have a value, the end must
 * come after the start, as the date window condition's "until" after its "from". It sits on the class whose fields they
 * are, a block type's, a layout's or a condition kind's or source's parameters class, and publish checks it against
 * their FieldValues, reporting it at the end field:
 *
 *     #[EndsAfterStart(start: 'from', end: 'until')]
 *     final class DateWindowParams
 *
 * See "Validation" in docs/spec/publishing.md
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class EndsAfterStart extends Constraint
{
    /** Its parameters %start% and %end% are the fields' labels */
    public string $message = 'setono_sylius_cms.field.ends_before_start';

    /**
     * @param string $start the name of the field that starts the span
     * @param string $end the name of the field that ends it
     * @param list<string>|null $groups
     */
    #[HasNamedArguments]
    public function __construct(
        public string $start,
        public string $end,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
