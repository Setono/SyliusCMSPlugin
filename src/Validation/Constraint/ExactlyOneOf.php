<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Exactly one of these fields of a block type has a value, as the Video block holds either a video or a YouTube or
 * Vimeo URL. It sits on the block type's class, and publish checks it against the block's FieldValues in each locale:
 *
 *     #[BlockType(code: 'app_promo', …)]
 *     #[ExactlyOneOf(fields: ['coupon', 'link'])]
 *     final class Promo
 *
 * See "Validation" in docs/spec/publishing.md
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ExactlyOneOf extends Constraint
{
    /** Its parameter %fields% lists the fields' labels */
    public string $message = 'setono_sylius_cms.block.exactly_one_of';

    /** @var list<string> */
    public array $fields;

    /**
     * @param list<string> $fields the names of two or more of the block type's fields
     * @param list<string>|null $groups
     */
    #[HasNamedArguments]
    public function __construct(array $fields, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (count($fields) < 2) {
            throw new ConstraintDefinitionException(sprintf('The %s constraint needs two fields or more, got %d', self::class, count($fields)));
        }

        parent::__construct(null, $groups, $payload);

        $this->fields = $fields;
        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
