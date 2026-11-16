import { ChoiceControl } from './ChoiceControl';
import { RichTextControl } from './RichTextControl';
import { TextareaControl } from './TextareaControl';
import { TextControl } from './TextControl';
import { ToggleControl } from './ToggleControl';
import type { FieldControl } from './types';

/** The control for each field type, keyed by the schema's x-cms-type. */
export const fieldControls: Readonly<Record<string, FieldControl>> = {
    text: TextControl,
    textarea: TextareaControl,
    rich_text: RichTextControl,
    choice: ChoiceControl,
    toggle: ToggleControl,
};

export function controlFor(fieldType: string): FieldControl | undefined {
    return Object.hasOwn(fieldControls, fieldType) ? fieldControls[fieldType] : undefined;
}
