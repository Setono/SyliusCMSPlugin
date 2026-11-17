import type { FieldSchema } from '../types';
import { CatalogControl } from './CatalogControl';
import { CatalogListControl } from './CatalogListControl';
import { ChoiceControl } from './ChoiceControl';
import { CodeControl } from './CodeControl';
import { controlTag, CustomElementControl } from './CustomElementControl';
import { DateControl } from './DateControl';
import { DateTimeControl } from './DateTimeControl';
import { LinkControl } from './LinkControl';
import { MediaControl } from './MediaControl';
import { MediaListControl } from './MediaListControl';
import { NumberControl } from './NumberControl';
import { RichTextControl } from './RichTextControl';
import { TextareaControl } from './TextareaControl';
import { TextControl } from './TextControl';
import { ToggleControl } from './ToggleControl';
import type { FieldControl } from './types';

/** The control for each shipped field type, keyed by the schema's x-cms-type. */
export const fieldControls: Readonly<Record<string, FieldControl>> = {
    text: TextControl,
    textarea: TextareaControl,
    rich_text: RichTextControl,
    choice: ChoiceControl,
    toggle: ToggleControl,
    number: NumberControl,
    date: DateControl,
    date_time: DateTimeControl,
    media: MediaControl,
    media_list: MediaListControl,
    link: LinkControl,
    product: CatalogControl,
    products: CatalogListControl,
    taxon: CatalogControl,
    taxons: CatalogListControl,
    entries: CatalogListControl,
    code: CodeControl,
};

/** A field's control: the custom element a project's own field type names, or else its shipped field type's control. */
export function controlFor(schema: FieldSchema): FieldControl | undefined {
    if (controlTag(schema) !== null) {
        return CustomElementControl;
    }

    const fieldType = schema['x-cms-type'];

    return Object.hasOwn(fieldControls, fieldType) ? fieldControls[fieldType] : undefined;
}
