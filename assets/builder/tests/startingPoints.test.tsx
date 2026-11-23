import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { createApi, type BuilderApi, type Fetch } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { ContentTypeDefinition, EntryResponse, EntryStatus, PresenceResponse, PresetContent, PresetDetails, PresetIndex, SavedPreset, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { block, blockOf, entry, registry, settings, strings } from './fixtures';

const startingPointStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.entry_title': 'Title',
    'setono_sylius_cms.builder.save_as_preset': 'Save as preset',
    'setono_sylius_cms.builder.save_as_preset_help': 'The blocks are saved as a preset.',
    'setono_sylius_cms.builder.save_as_starting_point': 'Save as starting point',
    'setono_sylius_cms.builder.save_as_starting_point_help': 'The whole tree, with its layout, is saved as a starting point.',
    'setono_sylius_cms.builder.save_as_starting_point_empty': 'Add blocks to save them as a starting point.',
    'setono_sylius_cms.builder.preset_name': 'Name',
    'setono_sylius_cms.builder.preset_category': 'Category',
    'setono_sylius_cms.builder.preset_description': 'Description',
    'setono_sylius_cms.builder.save_preset': 'Save preset',
    'setono_sylius_cms.builder.replace_a_preset': 'Or replace the blocks of a preset',
    'setono_sylius_cms.builder.replace_a_starting_point': 'Or replace a starting point',
    'setono_sylius_cms.builder.replace_preset': 'Replace %name%',
};

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
};

const LAUNCH = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

const PROMO = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b';

function saved(id: string, details: Partial<PresetDetails>): SavedPreset {
    return { id, name: 'Launch page', category: null, description: null, changedAt: '2026-11-20T10:00:00Z', ...details };
}

function fakeApi(blocks: StoredBlock[], index: PresetIndex = { presets: [], startingPoints: [], categories: [] }, contentTypes: ContentTypeDefinition[] = registry.contentTypes) {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, contentTypes, strings: startingPointStrings })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: { ...entry(blocks), contentType: contentTypes[0]?.code ?? 'page' }, settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(() => Promise.reject(new Error('Not discarded in this test'))),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        presets: vi.fn(async (_parent: string | null): Promise<PresetIndex> => index),
        insertPreset: vi.fn(async () => ({ blocks: [], notices: [] })),
        createPreset: vi.fn(async (_content: PresetContent, details: PresetDetails): Promise<SavedPreset> => saved(LAUNCH, details)),
        replacePreset: vi.fn(async (id: string, _content: PresetContent): Promise<SavedPreset> => saved(id, {})),
        updatePreset: vi.fn(async (id: string, details: Partial<PresetDetails>): Promise<SavedPreset> => saved(id, details)),
        deletePreset: vi.fn(async (_id: string): Promise<void> => undefined),
        presetExportUrl: (id: string) => `/admin/cms/api/presets/${id}/export`,
    } satisfies BuilderApi;
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function settingsPane(): HTMLElement {
    return screen.getByRole('complementary');
}

describe('saving the whole tree as a starting point from the entry settings', () => {
    it('should save the tree, with its layout, as a starting point for the entry’s content type, once the unsaved changes are sent', async () => {
        const api = fakeApi([block('a', { text: { en_US: 'Winter sale' } }), blockOf('rich_text', 'b')]);
        await openBuilder(api);
        fireEvent.change(within(settingsPane()).getByRole('textbox', { name: 'Title' }), { target: { value: 'Winter sale' } });

        fireEvent.click(within(settingsPane()).getByRole('button', { name: 'Save as starting point' }));

        const dialog = within(await screen.findByRole('dialog', { name: 'Save as starting point' }));
        expect(dialog.getByText('The whole tree, with its layout, is saved as a starting point.')).toBeInTheDocument();
        fireEvent.change(dialog.getByRole('textbox', { name: 'Name' }), { target: { value: 'Launch page' } });
        fireEvent.change(dialog.getByRole('combobox', { name: 'Category' }), { target: { value: 'Pages' } });
        fireEvent.click(dialog.getByRole('button', { name: 'Save preset' }));

        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Save as starting point' })).toBeNull());
        expect(api.createPreset).toHaveBeenCalledWith('tree', { name: 'Launch page', category: 'Pages', description: null });
        expect(api.saveChanges.mock.invocationCallOrder[0]).toBeLessThan(api.createPreset.mock.invocationCallOrder[0] ?? 0);
    });

    it("should replace an editor starting point of the entry's content type, and offer no block preset or code preset to replace", async () => {
        const api = fakeApi([block('a', { text: { en_US: 'Winter sale' } })], {
            presets: [{ id: PROMO, fromCode: false, name: 'Promo', category: null, description: null, thumbnail: null, fits: true, reasons: [], issues: [], blocks: [] }],
            startingPoints: [
                { id: 'landing_page', fromCode: true, name: 'Landing page', category: 'Pages', description: null },
                { id: LAUNCH, fromCode: false, name: 'Launch page', category: 'Pages', description: null },
            ],
            categories: ['Pages'],
        });
        await openBuilder(api);
        fireEvent.click(within(settingsPane()).getByRole('button', { name: 'Save as starting point' }));
        const dialog = within(await screen.findByRole('dialog', { name: 'Save as starting point' }));

        expect(await dialog.findByRole('heading', { name: 'Or replace a starting point' })).toBeInTheDocument();
        expect(dialog.queryByRole('button', { name: 'Replace Promo' })).toBeNull();
        expect(dialog.queryByRole('button', { name: 'Replace Landing page' })).toBeNull();
        fireEvent.click(dialog.getByRole('button', { name: 'Replace Launch page' }));

        await waitFor(() => expect(api.replacePreset).toHaveBeenCalledWith(LAUNCH, 'tree'));
        expect(api.createPreset).not.toHaveBeenCalled();
    });

    it('should be disabled while the tree has no blocks', async () => {
        await openBuilder(fakeApi([]));

        const save = within(settingsPane()).getByRole('button', { name: 'Save as starting point' });
        expect(save).toBeDisabled();
        expect(save).toHaveAccessibleDescription('Add blocks to save them as a starting point.');
    });

    it('should not be offered for a content type without a block tree', async () => {
        const storeLocation: ContentTypeDefinition = { code: 'store_location', label: 'Store location', blocks: null, prefix: 'stores', layouts: [], fields: [] };
        await openBuilder(fakeApi([], undefined, [storeLocation]));
        expect(within(settingsPane()).queryByRole('button', { name: 'Save as starting point' })).toBeNull();
    });

    it('should not be offered without the editor presets’ endpoints', async () => {
        const { createPreset: _create, replacePreset: _replace, updatePreset: _update, deletePreset: _delete, presetExportUrl: _export, ...withoutEditing } = fakeApi([block('a')]);
        await openBuilder(withoutEditing);

        expect(within(settingsPane()).queryByRole('button', { name: 'Save as starting point' })).toBeNull();
    });
});

describe('the starting points’ endpoints', () => {
    const withEndpoints: BuilderConfig = {
        ...config,
        endpoints: {
            ...config.endpoints,
            presets: '/presets?entry=e1',
            presetInsert: '/presets/00000000-0000-4000-8000-000000000000/insert',
            presetCreate: '/presets',
            preset: '/presets/00000000-0000-4000-8000-000000000000',
            presetExport: '/presets/00000000-0000-4000-8000-000000000000/export',
        },
    };

    function recordingFetch(body: unknown) {
        const calls: { url: string; init: RequestInit | undefined }[] = [];
        const fetchFn: Fetch = async (url, init) => {
            calls.push({ url, init });

            return new Response(JSON.stringify(body), { status: 200 });
        };

        return { calls, fetchFn };
    }

    it('should save and replace the whole tree as a starting point', async () => {
        const { calls, fetchFn } = recordingFetch({ preset: saved(LAUNCH, {}) });
        const api = createApi(withEndpoints, fetchFn);

        await api.createPreset?.('tree', { name: 'Launch page', category: null, description: null });
        await api.replacePreset?.(LAUNCH, 'tree');

        expect(calls.map(({ url, init }) => [init?.method, url, init?.body])).toEqual([
            ['POST', '/presets', JSON.stringify({ entry: config.entryUuid, startingPoint: true, name: 'Launch page', category: null, description: null })],
            ['PUT', `/presets/${LAUNCH}`, JSON.stringify({ entry: config.entryUuid, startingPoint: true })],
        ]);
    });

    it("should answer with the entry's content type's starting points beside the presets", async () => {
        const startingPoint = { id: LAUNCH, fromCode: false, name: 'Launch page', category: null, description: null };
        const listed = recordingFetch({ presets: [], startingPoints: [startingPoint], categories: [] });

        await expect(createApi(withEndpoints, listed.fetchFn).presets?.(null)).resolves.toEqual({ presets: [], startingPoints: [startingPoint], categories: [] });
    });
});
