import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { FieldControlBuilder } from '../src/form/CustomElementControl';
import { FieldRow } from '../src/form/FieldRow';
import { createTranslator, TranslatorProvider } from '../src/i18n';
import type { MediaApi } from '../src/media/api';
import { MediaPickerProvider } from '../src/media/picker';
import type { MediaItem } from '../src/media/types';
import type { FieldSchema, JsonValue } from '../src/types';
import { fakeSearch, renderField } from './fixtures';
import { libraryConfig, mediaItem } from './media/fixtures';

/**
 * A project's field control, as a project writes one: a custom element that shows what the builder sets on it and
 * dispatches cms-change from inside itself, so the event bubbles to the element the builder listens on
 */
class OpeningHoursElement extends HTMLElement {
    // Accessors on the prototype, which count how often the builder sets each
    declare value: JsonValue | null;
    declare field: FieldSchema | null;
    declare locale: string | null;
    declare builder: FieldControlBuilder | null;
    /** How often the builder set each property. */
    readonly sets: Record<string, number> = {};

    static {
        for (const property of ['value', 'field', 'locale', 'builder'] as const) {
            Object.defineProperty(this.prototype, property, {
                get(this: OpeningHoursElement & { _props?: Record<string, unknown> }) {
                    return this._props?.[property] ?? null;
                },
                set(this: OpeningHoursElement & { _props?: Record<string, unknown> }, next: unknown) {
                    this._props = { ...this._props, [property]: next };
                    this.sets[property] = (this.sets[property] ?? 0) + 1;
                },
            });
        }
    }

    connectedCallback(): void {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Close on Sundays';
        button.addEventListener('click', () => {
            button.dispatchEvent(new CustomEvent('cms-change', { bubbles: true, detail: { value: { sunday: null, monday: '9-17' } } }));
        });
        this.append(button);
    }
}

customElements.define('test-opening-hours', OpeningHoursElement);

const strings = {
    'setono_sylius_cms.builder.unsupported_field_type': 'This field type isn’t supported here.',
    'setono_sylius_cms.builder.media.choose_count': 'Choose %count%',
    ...libraryConfig.strings,
};

function schema(control: string): FieldSchema {
    return {
        type: 'object',
        'x-cms-type': 'app_opening_hours',
        'x-cms-control': control,
        'x-cms-label': 'Opening hours',
        'x-cms-help': 'When the store is open',
        'x-cms-required': false,
        'x-cms-translatable': false,
        'x-cms-options': { days: { monday: 'Monday', sunday: 'Sunday' } },
    };
}

const openingHours = schema('test-opening-hours');

const beach = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01', { name: 'Beach' });
const forest = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02', { name: 'Forest' });
const prices = mediaItem('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d03', { name: 'Prices', kind: 'document', mimeType: 'application/pdf', url: '/media/cms/prices.pdf' });

/** A media library with these media items, which lists those of the kinds asked for. */
function fakeMediaApi(items: MediaItem[] = [beach, forest, prices]): MediaApi {
    const unsupported = async (): Promise<never> => {
        throw new Error('Not here');
    };

    return {
        list: vi.fn(async (filters) => {
            const kinds = filters.kind === null ? (filters.kinds ?? null) : [filters.kind];
            const shown = items.filter((item) => kinds === null || kinds.includes(item.kind));

            return { items: shown, page: 1, pages: 1, total: shown.length };
        }),
        find: vi.fn(async (uuids: readonly string[]) => items.filter((item) => uuids.includes(item.uuid))),
        upload: unsupported,
        save: unsupported,
        uses: vi.fn(async () => []),
        replace: unsupported,
        remove: unsupported,
        folders: vi.fn(async () => []),
        createFolder: unsupported,
        updateFolder: unsupported,
    };
}

function renderOpeningHours(initial?: JsonValue, fieldSchema: FieldSchema = openingHours) {
    const search = fakeSearch({ product: [{ ref: 'MUG', name: 'Mug', thumbnail: '/media/cache/mug.jpg' }] });
    const { onChange } = renderField(fieldSchema, {
        initial,
        locale: 'de_DE',
        strings: { ...strings, 'app.opening_hours.closed': 'Closed on %day%' },
        search,
        providers: (field) => (
            <MediaPickerProvider api={fakeMediaApi()} config={{ locales: libraryConfig.locales, media: libraryConfig.media }}>
                {field}
            </MediaPickerProvider>
        ),
    });

    return { onChange, search };
}

function control(): OpeningHoursElement {
    return screen.getByRole('group', { name: 'Opening hours' }) as OpeningHoursElement;
}

function picker() {
    return within(screen.getByRole('dialog', { name: 'Choose from the media library' }));
}

describe('a field of a project’s own field type', () => {
    it('should render its field control, the custom element its schema names, as a group its label names', () => {
        renderOpeningHours();

        const element = control();
        expect(element.tagName).toBe('TEST-OPENING-HOURS');
        expect(element).toBeInstanceOf(OpeningHoursElement);
        expect(element).toHaveAccessibleDescription('When the store is open');
    });

    it('should set the stored value, the field’s schema, the edited locale and the builder on the custom element', () => {
        renderOpeningHours({ monday: '9-17' });

        const element = control();
        expect(element.value).toEqual({ monday: '9-17' });
        expect(element.field).toEqual(openingHours);
        expect(element.locale).toBe('de_DE');
        expect(element.builder).toEqual({ search: expect.any(Function), pickMedia: expect.any(Function), trans: expect.any(Function) });
    });

    it('should set null as the value of a field with none', () => {
        renderOpeningHours();

        expect(control().value).toBeNull();
        expect(control().sets.value).toBe(1);
    });

    it('should take the value of a cms-change event that bubbles from inside the custom element', () => {
        const { onChange } = renderOpeningHours({ monday: '9-17' });

        fireEvent.click(within(control()).getByRole('button', { name: 'Close on Sundays' }));

        expect(onChange).toHaveBeenCalledWith({ sunday: null, monday: '9-17' });
    });

    it('should not set back the value a cms-change event just gave, so the field control keeps its own state', () => {
        renderOpeningHours({ monday: '9-17' });
        const element = control();

        fireEvent.click(within(element).getByRole('button', { name: 'Close on Sundays' }));

        expect(element.sets.value).toBe(1);
        expect(element.value).toEqual({ monday: '9-17' });
    });

    it('should remove the value when a cms-change event gives none', () => {
        const { onChange } = renderOpeningHours({ monday: '9-17' });

        act(() => {
            control().dispatchEvent(new CustomEvent('cms-change', { bubbles: true, detail: {} }));
        });

        expect(onChange).toHaveBeenCalledWith(null);
    });

    it('should set the value again when it changes from outside, such as by undo', () => {
        const onChange = vi.fn();
        const row = (value: JsonValue) => (
            <TranslatorProvider value={createTranslator(strings)}>
                <FieldRow id="field" schema={openingHours} value={value} locale="en_US" onChange={onChange} />
            </TranslatorProvider>
        );
        const { rerender } = render(row({ monday: '9-17' }));

        act(() => {
            control().dispatchEvent(new CustomEvent('cms-change', { bubbles: true, detail: { value: { monday: '10-18' } } }));
        });
        rerender(row({ monday: '10-18' }));
        expect(control().sets.value).toBe(1);

        rerender(row({ monday: '9-17' }));
        expect(control().value).toEqual({ monday: '9-17' });
        expect(control().sets.value).toBe(2);
    });

    it('should wait until its custom element is defined, and then set the properties on the defined element', async () => {
        renderOpeningHours({ monday: '9-17' }, schema('test-late-opening-hours'));

        expect(screen.getByText('This field type isn’t supported here.')).toBeInTheDocument();

        class LateOpeningHoursElement extends OpeningHoursElement {}
        act(() => {
            customElements.define('test-late-opening-hours', LateOpeningHoursElement);
        });

        const element = (await screen.findByRole('group', { name: 'Opening hours' })) as OpeningHoursElement;
        expect(element).toBeInstanceOf(LateOpeningHoursElement);
        expect(element.value).toEqual({ monday: '9-17' });
        expect(element.locale).toBe('de_DE');
        expect(element.sets).toEqual({ field: 1, locale: 1, builder: 1, value: 1 });
    });
});

describe('the builder a field control gets', () => {
    it('should search what pickers search, by kind', async () => {
        const { search } = renderOpeningHours();

        await expect(control().builder?.search('product', 'mu')).resolves.toEqual([{ ref: 'MUG', name: 'Mug', thumbnail: '/media/cache/mug.jpg' }]);
        expect(search.search).toHaveBeenCalledWith('product', 'mu');
    });

    it('should open the media library as a picker of the kinds asked for, and resolve to the UUIDs of the media items picked', async () => {
        renderOpeningHours();

        let picked: Promise<string[] | null> | undefined;
        act(() => {
            picked = control().builder?.pickMedia({ kinds: ['image'], multiple: true });
        });

        fireEvent.click(await picker().findByRole('button', { name: /\bForest\b/ }));
        fireEvent.click(await picker().findByRole('button', { name: /\bBeach\b/ }));
        expect(picker().queryByRole('button', { name: /\bPrices\b/ })).not.toBeInTheDocument();
        fireEvent.click(picker().getByRole('button', { name: 'Choose 2' }));

        await expect(picked).resolves.toEqual([forest.uuid, beach.uuid]);
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    });

    it('should resolve to null when the media picker is cancelled', async () => {
        renderOpeningHours();

        let picked: Promise<string[] | null> | undefined;
        act(() => {
            picked = control().builder?.pickMedia({ kinds: ['image'], multiple: false });
        });
        fireEvent.click(await picker().findByRole('button', { name: 'Cancel' }));

        await expect(picked).resolves.toBeNull();
    });

    it('should translate a UI string with its placeholders filled in, or else give the key', () => {
        renderOpeningHours();

        expect(control().builder?.trans('app.opening_hours.closed', { '%day%': 'Sunday' })).toBe('Closed on Sunday');
        expect(control().builder?.trans('app.opening_hours.unknown')).toBe('app.opening_hours.unknown');
    });
});
