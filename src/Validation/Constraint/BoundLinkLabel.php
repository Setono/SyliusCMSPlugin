<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * A Link field whose ref shows a binding has a label, as a Button's does: a bound link has no target name to show
 * instead. It sits on the block type's class, and publish checks it against the block's FieldValues in each locale,
 * with the label that locale reads along its fallback chain:
 *
 *     #[BlockType(code: 'button', …)]
 *     #[BoundLinkLabel(field: 'link')]
 *     final class Button
 *
 * See "Button" in docs/spec/blocks.md and "Links" in docs/spec/content-model.md
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class BoundLinkLabel extends Constraint
{
    public string $message = 'setono_sylius_cms.block.bound_link_label';

    /**
     * @param string $field the name of the block type's Link field
     * @param list<string>|null $groups
     */
    #[HasNamedArguments]
    public function __construct(
        public string $field,
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
