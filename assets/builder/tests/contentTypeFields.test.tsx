import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { Change } from '../src/changeSet';
import type { BuilderConfig } from '../src/config';
import type { ContentTypeDefinition, Entry, EntryResponse, EntryStatus, LayoutDefinition, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { page, registry, settings } from './fixtures';

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    overviewUrl: '/admin/cms/entry/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    endpoints: {
        registry: '/registry',
        entry: '/entry',
        validate: '/validate',
        publish: '/publish',
        discard: '/discard',
        schedule: '/schedule',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
    timeZone: 'UTC',
    locales: [
        { code: 'en_US', name: 'English', fallbackChain: ['en_US'] },
        { code: 'da_DK', name: 'Danish', fallbackChain: ['da_DK', 'en_US'] },
    ],
};

const store: LayoutDefinition = { code: 'store', label: 'Store', version: 1, thumbnail: null, default: true, disabled: false, shows: ['title', 'city'], fields: [] };

/** A project's own content type with its own URL and no block tree: a shared city and translatable directions. */
const storeLocation: ContentTypeDefinition = {
    code: 'store_location',
    label: 'Store location',
    blocks: null,
    prefix: 'stores',
    layouts: [store],
    fields: [
        { name: 'city', schema: { type: 'string', 'x-cms-type': 'text', 'x-cms-label': 'City', 'x-cms-help': null, 'x-cms-required': true, 'x-cms-translatable': false } },
        { name: 'directions', schema: { type: 'string', 'x-cms-type': 'textarea', 'x-cms-label': 'Directions', 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': true } },
    ],
};

function aarhus(): Entry {
    return {
        uuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
        contentType: 'store_location',
        version: 1,
        createdAt: '2026-11-14T10:00:00Z',
        fields: { city: 'Aarhus', noindex: false },
        translations: {
            en_US: { shown: true, fields: { directions: 'Turn left', slug: 'aarhus', title: 'Aarhus' }, translated: {} },
        },
        tree: { layout: { code: 'store', version: 1, fields: {}, translated: {} }, blocks: [] },
    };
}

function fakeApi(errors: ValidationError[] = []) {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, contentTypes: [page, storeLocation] })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: aarhus(), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'unpublished_changes', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => errors),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: aarhus(), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/stores/aarhus'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    };
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function settingsPanel() {
    return within(within(screen.getByRole('complementary')).getByRole('tabpanel', { name: 'Settings' }));
}

describe("a content type's own fields", () => {
    it('should render them in the entry settings from their schemas, with the values the entry has in the edited locale', async () => {
        await openBuilder(fakeApi());

        expect(settingsPanel().getByLabelText('City')).toHaveValue('Aarhus');
        expect(settingsPanel().getByLabelText('Directions')).toHaveValue('Turn left');
        expect(settingsPanel().getAllByText('setono_sylius_cms.builder.all_languages')).toHaveLength(1);
    });

    it('should save a shared field without a locale and a translatable one in the edited locale', async () => {
        const api = fakeApi();
        await openBuilder(api);

        fireEvent.change(settingsPanel().getByLabelText('City'), { target: { value: 'Aarhus C' } });
        fireEvent.change(settingsPanel().getByLabelText('Directions'), { target: { value: 'Turn right' } });

        await waitFor(() =>
            expect(api.saveChanges.mock.calls.flatMap(([changes]) => changes)).toEqual(
                expect.arrayContaining([
                    { op: 'set_entry_value', field: 'city', locale: null, value: 'Aarhus C' },
                    { op: 'set_entry_value', field: 'directions', locale: 'en_US', value: 'Turn right' },
                ]),
            ),
        );
    });

    it('should show what publish refuses in a field under it, and not again above the tabs', async () => {
        await openBuilder(fakeApi([{ block: null, field: 'city', locale: null, message: 'This value should not be blank.' }]));

        await waitFor(() => expect(settingsPanel().getByText('This value should not be blank.')).toBeInTheDocument());
        expect(screen.queryByText('city: This value should not be blank.')).not.toBeInTheDocument();
    });

    it('should hide the block tree of a content type without one', async () => {
        await openBuilder(fakeApi());

        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Add block' })).not.toBeInTheDocument();
    });
});
