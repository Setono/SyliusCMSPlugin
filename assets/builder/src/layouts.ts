/**
 * The entry's layout: which one it renders in, which ones editors can pick, and switching from one to another. Pure
 * functions, with no React and no I/O. See "Layouts" in docs/spec/layouts-and-presets.md
 */
import type { FieldDefinition, JsonValue, LayoutDefinition, StoredLayout, TranslationState } from './types';

/** The definition of the layout a tree stores, a disabled one too, or undefined when the code no longer has it. */
export function layoutOf(layouts: readonly LayoutDefinition[], stored: StoredLayout | null): LayoutDefinition | undefined {
    return stored === null ? undefined : layouts.find((layout) => layout.code === stored.code);
}

/**
 * The layout the entry renders in: the one its tree stores, unless that one was removed from code or disabled, and then
 * the content type's default. Undefined for a content type without layouts.
 */
export function renderedLayout(layouts: readonly LayoutDefinition[], stored: StoredLayout | null): LayoutDefinition | undefined {
    const own = layoutOf(layouts, stored);

    return own !== undefined && !own.disabled ? own : layouts.find((layout) => layout.default);
}

/** Whether the entry's layout was removed from code or disabled, so it renders in the default layout until an editor picks one. */
export function isRemovedLayout(layouts: readonly LayoutDefinition[], stored: StoredLayout | null): boolean {
    const rendered = renderedLayout(layouts, stored);

    return stored !== null && rendered !== undefined && rendered.code !== stored.code;
}

/** The layouts editors can pick: every one that isn't disabled. */
export function pickableLayouts(layouts: readonly LayoutDefinition[]): LayoutDefinition[] {
    return layouts.filter((layout) => !layout.disabled);
}

/**
 * The tree's layout once switched to another. Values carry over to the new layout's fields with the same name and field
 * type, together with their translation state, and the rest are dropped. A layout the code no longer has has fields of
 * unknown types, so none of its values carry over. See "Picking and switching" in docs/spec/layouts-and-presets.md
 *
 * @param from the definition of the layout the tree is in, or undefined when the code no longer has it
 */
export function switchLayout(stored: StoredLayout | null, from: LayoutDefinition | undefined, to: LayoutDefinition): StoredLayout {
    const fields: Record<string, JsonValue> = {};
    const translated: TranslationState = {};

    for (const field of to.fields) {
        const value = stored?.fields[field.name];
        const previous = from?.fields.find(({ name }) => name === field.name);
        if (value === undefined || previous === undefined || !sameFieldType(previous, field)) {
            continue;
        }

        fields[field.name] = structuredClone(value);
        const sources = stored?.translated[field.name];
        if (field.schema['x-cms-translatable'] && sources !== undefined) {
            translated[field.name] = structuredClone(sources);
        }
    }

    return { code: to.code, version: to.version, fields, translated };
}

/** The same field type, translatable in both or shared in both, so the stored value fits as it is. */
function sameFieldType(a: FieldDefinition, b: FieldDefinition): boolean {
    return a.schema['x-cms-type'] === b.schema['x-cms-type'] && a.schema['x-cms-translatable'] === b.schema['x-cms-translatable'];
}
