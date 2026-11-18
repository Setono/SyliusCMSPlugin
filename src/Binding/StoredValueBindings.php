<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\InnerBindingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Tree\StoredValue;

/**
 * The bindings a stored value of a block's or the layout's field holds: the whole value, when the field is bound, or
 * those inside it, as a Link's ref and rich text's tokens hold them. See "Bindings" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class StoredValueBindings
{
    public function __construct(private FieldHandlerLocator $fieldHandlers)
    {
    }

    /**
     * @return iterable<StoredBinding>
     */
    public function of(StoredValue $value): iterable
    {
        $binding = BoundValue::of($value->field, $value->value);
        if (null !== $binding) {
            return [$binding];
        }

        $handler = $this->fieldHandlers->get($value->field->field);

        return $handler instanceof InnerBindingFieldHandlerInterface && !$value->isNotByLocale() ? $handler->bindings($value->field->field, $value->value) : [];
    }
}
