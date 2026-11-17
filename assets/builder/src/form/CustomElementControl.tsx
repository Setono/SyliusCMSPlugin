/**
 * The field controls of projects' own field types: custom elements, defined by the scripts the builder page loads from
 * setono_sylius_cms.builder.scripts. The builder sets four properties on the element and listens for cms-change, as
 * docs/spec/extending.md#field-controls describes. That's a public contract.
 */
import { createElement, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { useTranslator, type MessageKey, type Translate } from '../i18n';
import { useMediaPicker, type MediaPicker } from '../media/picker';
import type { MediaKind } from '../media/types';
import type { SearchApi, SearchResult } from '../search/api';
import { useSearch } from '../search/context';
import type { FieldSchema, JsonValue } from '../types';
import { normalizeValue } from '../values';
import type { FieldControlProps } from './types';
import { UnsupportedControl } from './UnsupportedControl';

/** The event a field control dispatches, bubbling, with the new value in detail.value, whenever its value changes. */
export const CHANGE_EVENT = 'cms-change';

export interface PickMediaOptions {
    /** The kinds of media item the picker offers. Images when left out. */
    kinds?: readonly MediaKind[];
    /** Whether several media items are picked. One when left out. */
    multiple?: boolean;
}

/** What a field control reaches the builder through: its `.builder`. */
export interface FieldControlBuilder {
    /** What of the kind matches the query, best first: product, taxon, entry, media or a link target's code. */
    search(kind: string, query: string): Promise<SearchResult[]>;
    /** Opens the media library as a picker, and resolves to the UUIDs of the media items picked, or null when it's cancelled. */
    pickMedia(options?: PickMediaOptions): Promise<string[] | null>;
    /** A UI string in the admin user's locale, with its placeholders, such as %count%, filled in. The key when there's none. */
    trans(key: string, params?: Readonly<Record<string, string | number>>): string;
}

/** The properties the builder sets on a field control. */
export interface FieldControlElement extends HTMLElement {
    /** The stored value, for the edited locale when the field is translatable, or null when it has none. */
    value: JsonValue | null;
    /** The field's schema, with its x-cms-* keywords. */
    field: FieldSchema;
    /** The locale being edited. */
    locale: string;
    builder: FieldControlBuilder;
}

/** The custom element's tag name a field's schema names, for a project's own field type. */
export function controlTag(schema: FieldSchema): string | null {
    const tag = schema['x-cms-control'];

    return typeof tag === 'string' && tag !== '' ? tag : null;
}

/** The field control of a project's own field type, the custom element its schema names, as a group its label names. */
export function CustomElementControl(props: FieldControlProps) {
    const tag = controlTag(props.schema);

    // Keyed by its tag name, so another field in its place gets an element of its own
    return tag === null ? <UnsupportedControl id={props.id} /> : <CustomElementHost key={tag} tag={tag} {...props} />;
}

function CustomElementHost({ tag, id, value, onChange, schema, locale, describedBy }: FieldControlProps & { tag: string }) {
    const defined = useDefined(tag);
    const builder = useFieldControlBuilder();
    const ref = useRef<FieldControlElement | null>(null);
    const latestOnChange = useRef(onChange);
    // The value the element's last cms-change gave, as JSON, which isn't set back on it, so it keeps its own state
    const given = useRef<string | null>(null);

    useLayoutEffect(() => {
        latestOnChange.current = onChange;
    });

    // In this order, so the element has its schema, locale and builder by the time it gets its value
    useLayoutEffect(() => {
        if (ref.current !== null) {
            ref.current.field = schema;
        }
    }, [defined, schema]);
    useLayoutEffect(() => {
        if (ref.current !== null) {
            ref.current.locale = locale;
        }
    }, [defined, locale]);
    useLayoutEffect(() => {
        if (ref.current !== null) {
            ref.current.builder = builder;
        }
    }, [defined, builder]);
    useLayoutEffect(() => {
        const element = ref.current;
        if (element === null) {
            return;
        }

        const next = value ?? null;
        if (JSON.stringify(next) === given.current) {
            return;
        }

        given.current = null;
        // A copy, as the store freezes the values it keeps
        element.value = structuredClone(next);
    }, [defined, value]);

    useEffect(() => {
        const element = ref.current;
        if (element === null) {
            return undefined;
        }

        const changed = (event: Event): void => {
            const detail: unknown = event instanceof CustomEvent ? event.detail : undefined;
            const next = typeof detail === 'object' && detail !== null && 'value' in detail ? ((detail as { value?: JsonValue }).value ?? null) : null;
            // As the store keeps it, which keeps an empty string or list as none
            given.current = JSON.stringify(normalizeValue(next));
            latestOnChange.current(structuredClone(next));
        };

        element.addEventListener(CHANGE_EVENT, changed);

        return () => element.removeEventListener(CHANGE_EVENT, changed);
    }, [defined]);

    if (!defined) {
        return <UnsupportedControl id={id} />;
    }

    return createElement(tag, { id, ref, role: 'group', 'aria-labelledby': `${id}-row-label`, 'aria-describedby': describedBy });
}

/**
 * Whether the custom element is defined. It's created only once it is, so the builder sets its properties on the
 * element's own class, which a property set on an element that's upgraded later would hide.
 */
function useDefined(tag: string): boolean {
    const [defined, setDefined] = useState(() => customElements.get(tag) !== undefined);

    useEffect(() => {
        if (defined) {
            return undefined;
        }

        let mounted = true;
        void customElements.whenDefined(tag).then(() => {
            if (mounted) {
                setDefined(true);
            }
        });

        return () => {
            mounted = false;
        };
    }, [tag, defined]);

    return defined;
}

function useFieldControlBuilder(): FieldControlBuilder {
    const search = useSearch();
    const picker = useMediaPicker();
    const translate = useTranslator();

    return useMemo(() => createFieldControlBuilder(search?.api ?? null, picker, translate), [search, picker, translate]);
}

const MEDIA_KINDS: readonly string[] = ['image', 'video', 'document'] satisfies MediaKind[];

/** A field control is plain JS, so it can ask for anything. */
function isMediaKind(kind: unknown): kind is MediaKind {
    return typeof kind === 'string' && MEDIA_KINDS.includes(kind);
}

/**
 * The builder a field control gets: the search pickers search with, the media picker and the UI strings. Without a
 * search nothing is found, and without a media picker nothing is picked.
 */
export function createFieldControlBuilder(search: SearchApi | null, picker: MediaPicker | null, translate: Translate): FieldControlBuilder {
    return {
        search: async (kind, query) => (search === null ? [] : search.search(kind, query)),
        pickMedia: async ({ kinds = ['image'], multiple = false } = {}) => {
            const picked = await picker?.pickMedia({ kinds: kinds.filter(isMediaKind), multiple: multiple === true });

            return picked === undefined || picked === null ? null : picked.map((item) => item.uuid);
        },
        trans: (key, params = {}) => translate(key as MessageKey, Object.fromEntries(Object.entries(params).map(([placeholder, text]) => [placeholder, String(text)]))),
    };
}
