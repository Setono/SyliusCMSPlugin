import { ChoiceWidget } from './ChoiceWidget';
import { TextWidget } from './TextWidget';
import type { FieldWidget } from './types';

/** The control for each field type, keyed by the schema's x-cms-type. */
export const fieldWidgets: Readonly<Record<string, FieldWidget>> = {
    text: TextWidget,
    choice: ChoiceWidget,
};

export function widgetFor(fieldType: string): FieldWidget | undefined {
    return Object.hasOwn(fieldWidgets, fieldType) ? fieldWidgets[fieldType] : undefined;
}
