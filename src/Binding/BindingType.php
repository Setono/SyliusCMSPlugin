<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

/**
 * The type of value a bindable property resolves to, which decides the fields it can be bound to: a field type lists the
 * binding types it accepts in #[FieldType(bindable:)]. See "Bindable properties" in docs/spec/extending.md
 */
enum BindingType: string
{
    case Text = 'text';
    case Number = 'number';
    case Url = 'url';
}
