import type { ComponentType } from 'react';
import type { FieldSchema, JsonValue } from '../types';

/**
 * What every field type's control gets. The shipped field types are React components; a project's own field type's
 * custom element gets the value, the schema and the locale as properties (form/CustomElementControl.tsx).
 */
export interface FieldControlProps {
    /** The control's id, which its label points at. */
    id: string;
    /** The stored value, in the edited locale when the field is translatable. Undefined when it has none. */
    value: JsonValue | undefined;
    /** Called with the new value. null, '' or [] removes it. */
    onChange: (value: JsonValue | null) => void;
    schema: FieldSchema;
    /** The locale being edited. */
    locale: string;
    /** The id of the field's help text, when it has one. */
    describedBy?: string | undefined;
    /** What it shows while it's empty: the value a translatable field falls back to. */
    placeholder?: JsonValue | undefined;
}

export type FieldControl = ComponentType<FieldControlProps>;
