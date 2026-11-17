<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A field type handler whose values hold their bindings inside them, as a Link's ref and rich text's tokens do, rather
 * than as the whole value. The plugin then leaves a field of its type to its handler, which resolves and validates the
 * bindings itself, and never reads its value as a binding. See "Bindings" in docs/spec/slots.md
 *
 * @internal
 */
interface InnerBindingFieldHandlerInterface extends FieldHandlerInterface
{
}
