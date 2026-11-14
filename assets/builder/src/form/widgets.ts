import { ChoiceWidget } from './ChoiceWidget';
import { RichTextWidget } from './RichTextWidget';
import { TextareaWidget } from './TextareaWidget';
import { TextWidget } from './TextWidget';
import { ToggleWidget } from './ToggleWidget';
import type { FieldWidget } from './types';

/** The control for each field type, keyed by the schema's x-cms-type. */
export const fieldWidgets: Readonly<Record<string, FieldWidget>> = {
    text: TextWidget,
    textarea: TextareaWidget,
    rich_text: RichTextWidget,
    choice: ChoiceWidget,
    toggle: ToggleWidget,
};

export function widgetFor(fieldType: string): FieldWidget | undefined {
    return Object.hasOwn(fieldWidgets, fieldType) ? fieldWidgets[fieldType] : undefined;
}
