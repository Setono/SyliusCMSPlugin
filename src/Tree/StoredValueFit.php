<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;

/**
 * Whether a stored value fits its field type: the misfit_value problem kind flags those that don't, and
 * setono:cms:preset:validate refuses them in preset files. See "Values that don't fit" in docs/spec/content-model.md
 *
 * @internal
 */
final readonly class StoredValueFit
{
    public function __construct(private FallbackValues $fallbackValues)
    {
    }

    /**
     * A translatable field's value that isn't a locale map doesn't fit, unless it's empty, and nor does a value its
     * field type doesn't take. A binding fits a field that can be bound, in its shape
     */
    public function doesNotFit(StoredValue $value): bool
    {
        if (BoundValue::isBound($value->field, $value->value)) {
            return null === BoundValue::of($value->field, $value->value);
        }

        if ($value->isNotByLocale()) {
            return '' !== $value->value && [] !== $value->value;
        }

        return !$this->fallbackValues->fits($value->field->field, $value->value);
    }
}
