import type { ComponentType } from 'react';
import type { FieldSchema, JsonValue } from '../types';

/**
 * What every field type's control gets. The shipped field types are React components; a project's own
 * field type will get the same as properties of its custom element.
 */
export interface FieldWidgetProps {
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
}

export type FieldWidget = ComponentType<FieldWidgetProps>;
