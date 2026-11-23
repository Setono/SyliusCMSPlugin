import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ApiError, createApi, type BuilderApi, type Fetch } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, PresenceResponse, PresetDetails, PresetIndex, PresetListing, SavedPreset, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { block, blockOf, entry, registry, settings, strings } from './fixtures';

const editorStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.blocks_tab': 'Blocks',
    'setono_sylius_cms.builder.presets_tab': 'Presets',
    'setono_sylius_cms.builder.search_presets': 'Search presets',
    'setono_sylius_cms.builder.preset_from_code': 'From code',
    'setono_sylius_cms.builder.preset_preview': 'Preview of %name%',
    'setono_sylius_cms.builder.insert_preset': 'Insert %name%',
    'setono_sylius_cms.builder.save_as_preset': 'Save as preset',
    'setono_sylius_cms.builder.save_as_preset_help': 'The blocks are saved as a preset.',
    'setono_sylius_cms.builder.preset_name': 'Name',
    'setono_sylius_cms.builder.preset_category': 'Category',
    'setono_sylius_cms.builder.preset_description': 'Description',
    'setono_sylius_cms.builder.save_preset': 'Save preset',
    'setono_sylius_cms.builder.replace_a_preset': 'Or replace the blocks of a preset',
    'setono_sylius_cms.builder.replace_preset': 'Replace %name%',
    'setono_sylius_cms.builder.edit_preset': 'Edit %name%',
    'setono_sylius_cms.builder.not_available_here': 'Not available here',
    'setono_sylius_cms.builder.delete_preset': 'Delete %name%',
    'setono_sylius_cms.builder.delete_preset_confirm': 'Delete %name%? The entries it was inserted into keep their blocks.',
    'setono_sylius_cms.builder.download_preset': 'Download %name%',
    'setono_sylius_cms.builder.preset_save': 'Save',
    'setono_sylius_cms.builder.preset_cancel': 'Cancel',
    'setono_sylius_cms.builder.preset_delete': 'Delete',
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

function listing(id: string, overrides: Partial<PresetListing> = {}): PresetListing {
    return { id, fromCode: false, name: id, category: null, description: null, thumbnail: null, fits: true, reasons: [], issues: [], blocks: [blockOf('heading', `${id}-heading`)], ...overrides };
}

function saved(id: string, details: Partial<PresetDetails>): SavedPreset {
    return { id, name: 'Promo', category: null, description: null, changedAt: '2026-11-20T10:00:00Z', ...details };
}

const PROMO = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';
const NOTE = '0193a5be-7401-7d3e-8f4a-5b6c7d8e9f0b';

function fakeApi(blocks: StoredBlock[], index: PresetIndex = { presets: [], startingPoints: [], categories: [] }) {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, strings: editorStrings })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
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
        createPreset: vi.fn(async (_blocks: readonly string[], details: PresetDetails): Promise<SavedPreset> => saved(PROMO, details)),
        replacePreset: vi.fn(async (id: string, _blocks: readonly string[]): Promise<SavedPreset> => saved(id, {})),
        updatePreset: vi.fn(async (id: string, details: Partial<PresetDetails>): Promise<SavedPreset> => saved(id, details)),
        deletePreset: vi.fn(async (_id: string): Promise<void> => undefined),
        presetExportUrl: (id: string) => `/admin/cms/api/presets/${id}/export`,
    } satisfies BuilderApi;
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

describe('saving blocks as a preset with ★', () => {
    it('should save the selected block with a name, a category suggested from those in use, and a description', async () => {
        const api = fakeApi([block('a', { text: { en_US: 'Winter sale' } }), block('b')], { presets: [], startingPoints: [], categories: ['Banners', 'Campaigns'] });
        await openBuilder(api);
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Winter sale' }));

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Save as preset' }));

        const dialog = within(await screen.findByRole('dialog', { name: 'Save as preset' }));
        fireEvent.change(dialog.getByRole('textbox', { name: 'Name' }), { target: { value: 'Promo' } });
        const category = dialog.getByRole('combobox', { name: 'Category' });
        await waitFor(() => expect([...(category.closest('div')?.querySelectorAll('datalist option') ?? [])].map((option) => option.getAttribute('value'))).toEqual(['Banners', 'Campaigns']));
        fireEvent.change(category, { target: { value: 'Campaigns' } });
        fireEvent.change(dialog.getByRole('textbox', { name: 'Description' }), { target: { value: 'For the winter sale' } });
        fireEvent.click(dialog.getByRole('button', { name: 'Save preset' }));

        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Save as preset' })).toBeNull());
        expect(api.createPreset).toHaveBeenCalledWith(['a'], { name: 'Promo', category: 'Campaigns', description: 'For the winter sale' });
    });

    it('should save a ⌘-click selection, leaving out an empty category and description', async () => {
        const api = fakeApi([block('a', { text: { en_US: 'First' } }), block('b', { text: { en_US: 'Second' } })]);
        await openBuilder(api);
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Second' }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading First' }), { metaKey: true });
        expect(within(selectionPane()).getByRole('heading', { name: '2 blocks selected' })).toBeInTheDocument();

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Save as preset' }));
        const dialog = within(await screen.findByRole('dialog', { name: 'Save as preset' }));
        fireEvent.change(dialog.getByRole('textbox', { name: 'Name' }), { target: { value: 'Two headings' } });
        fireEvent.change(dialog.getByRole('combobox', { name: 'Category' }), { target: { value: '  ' } });
        fireEvent.click(dialog.getByRole('button', { name: 'Save preset' }));

        await waitFor(() => expect(api.createPreset).toHaveBeenCalledWith(['b', 'a'], { name: 'Two headings', category: null, description: null }));
    });

    it('should send the unsaved changes before it saves, as the server reads the blocks as it has them', async () => {
        const api = fakeApi([block('a', { text: { en_US: 'Winter sale' } })]);
        await openBuilder(api);
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Winter sale' }));
        fireEvent.change(within(selectionPane()).getByRole('textbox', { name: 'Text' }), { target: { value: 'Spring sale' } });

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Save as preset' }));
        const dialog = within(await screen.findByRole('dialog', { name: 'Save as preset' }));
        fireEvent.change(dialog.getByRole('textbox', { name: 'Name' }), { target: { value: 'Promo' } });
        fireEvent.click(dialog.getByRole('button', { name: 'Save preset' }));

        await waitFor(() => expect(api.createPreset).toHaveBeenCalled());
        expect(api.saveChanges.mock.invocationCallOrder[0]).toBeLessThan(api.createPreset.mock.invocationCallOrder[0] ?? 0);
    });

    it("should replace an editor preset's blocks, offering only editor presets", async () => {
        const api = fakeApi([block('a', { text: { en_US: 'Winter sale' } })], {
            presets: [listing('hero', { name: 'Hero', fromCode: true }), listing(PROMO, { name: 'Promo' })],
            startingPoints: [],
            categories: [],
        });
        await openBuilder(api);
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Winter sale' }));
        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Save as preset' }));
        const dialog = within(await screen.findByRole('dialog', { name: 'Save as preset' }));

        fireEvent.click(await dialog.findByRole('button', { name: 'Replace Promo' }));

        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Save as preset' })).toBeNull());
        expect(api.replacePreset).toHaveBeenCalledWith(PROMO, ['a']);
        expect(dialog.queryByRole('button', { name: 'Replace Hero' })).toBeNull();
        expect(api.createPreset).not.toHaveBeenCalled();
    });

    it('should say why the server refused to save the preset', async () => {
        const api = fakeApi([block('a', { text: { en_US: 'Winter sale' } })]);
        api.createPreset.mockRejectedValueOnce(new ApiError('A preset needs a name.', 400));
        await openBuilder(api);
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Winter sale' }));
        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Save as preset' }));
        const dialog = within(await screen.findByRole('dialog', { name: 'Save as preset' }));
        fireEvent.change(dialog.getByRole('textbox', { name: 'Name' }), { target: { value: ' ' } });

        await act(async () => fireEvent.click(dialog.getByRole('button', { name: 'Save preset' })));

        expect(await dialog.findByRole('alert')).toHaveTextContent('A preset needs a name.');
        expect(screen.getByRole('dialog', { name: 'Save as preset' })).toBeInTheDocument();
    });

    it('should offer no ★ without the editor presets’ endpoints', async () => {
        const { createPreset: _create, replacePreset: _replace, updatePreset: _update, deletePreset: _delete, presetExportUrl: _export, ...withoutEditing } = fakeApi([block('a', { text: { en_US: 'Winter sale' } })]);
        await openBuilder(withoutEditing);
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Winter sale' }));

        expect(within(selectionPane()).queryByRole('button', { name: 'Save as preset' })).toBeNull();
    });
});

describe('changing an editor preset in the Presets tab', () => {
    async function openPresetsTab(api: BuilderApi) {
        await openBuilder(api);
        fireEvent.click(screen.getAllByRole('button', { name: 'Add block' })[0] as HTMLElement);
        const picker = within(await screen.findByRole('dialog', { name: 'Add block' }));
        fireEvent.click(picker.getByRole('tab', { name: 'Presets' }));

        return picker;
    }

    const index: PresetIndex = {
        presets: [listing('hero', { name: 'Hero', fromCode: true, category: 'Banners' }), listing(PROMO, { name: 'Promo', category: 'Banners', description: 'For the sale' })],
        startingPoints: [],
        categories: ['Banners', 'Campaigns'],
    };

    it('should offer Edit, Delete and Download for an editor preset only', async () => {
        const picker = await openPresetsTab(fakeApi([], index));

        await picker.findByRole('button', { name: 'Insert Promo' });
        expect(picker.getByRole('button', { name: 'Edit Promo' })).toBeInTheDocument();
        expect(picker.getByRole('button', { name: 'Delete Promo' })).toBeInTheDocument();
        expect(picker.getByRole('link', { name: 'Download Promo' })).toHaveAttribute('href', `/admin/cms/api/presets/${PROMO}/export`);
        expect(picker.queryByRole('button', { name: 'Edit Hero' })).toBeNull();
        expect(picker.queryByRole('link', { name: 'Download Hero' })).toBeNull();
    });

    it('should offer Edit, Delete and Download for an editor preset left out here too', async () => {
        const leftOut: PresetIndex = { ...index, presets: [...index.presets, listing(NOTE, { name: 'Product note', category: 'Products', fits: false, reasons: ['Needs: Product page'] })] };
        const picker = await openPresetsTab(fakeApi([], leftOut));

        const notAvailable = within(await picker.findByRole('list', { name: 'Not available here' }));
        expect(notAvailable.getByRole('button', { name: 'Edit Product note' })).toBeInTheDocument();
        expect(notAvailable.getByRole('button', { name: 'Delete Product note' })).toBeInTheDocument();
        expect(notAvailable.getByRole('link', { name: 'Download Product note' })).toHaveAttribute('href', `/admin/cms/api/presets/${NOTE}/export`);
        expect(notAvailable.queryByRole('button', { name: /Insert/ })).toBeNull();
    });

    it('should rename and recategorise it, and list the presets again', async () => {
        const api = fakeApi([], index);
        const picker = await openPresetsTab(api);

        fireEvent.click(await picker.findByRole('button', { name: 'Edit Promo' }));
        const form = within(picker.getByRole('form', { name: 'Edit Promo' }));
        expect(form.getByRole('textbox', { name: 'Description' })).toHaveValue('For the sale');
        fireEvent.change(form.getByRole('textbox', { name: 'Name' }), { target: { value: 'Spring promo' } });
        fireEvent.change(form.getByRole('combobox', { name: 'Category' }), { target: { value: 'Campaigns' } });
        fireEvent.click(form.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(api.updatePreset).toHaveBeenCalledWith(PROMO, { name: 'Spring promo', category: 'Campaigns', description: 'For the sale' }));
        await waitFor(() => expect(api.presets).toHaveBeenCalledTimes(2));
    });

    it('should delete it once the editor confirms', async () => {
        const api = fakeApi([], index);
        const picker = await openPresetsTab(api);

        fireEvent.click(await picker.findByRole('button', { name: 'Delete Promo' }));
        expect(picker.getByText('Delete Promo? The entries it was inserted into keep their blocks.')).toBeInTheDocument();
        fireEvent.click(picker.getByRole('button', { name: 'Cancel' }));
        expect(api.deletePreset).not.toHaveBeenCalled();

        fireEvent.click(picker.getByRole('button', { name: 'Delete Promo' }));
        fireEvent.click(picker.getByRole('button', { name: 'Delete' }));

        await waitFor(() => expect(api.deletePreset).toHaveBeenCalledWith(PROMO));
        await waitFor(() => expect(api.presets).toHaveBeenCalledTimes(2));
    });
});

describe("the editor presets' endpoints", () => {
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

    function recordingFetch(body: unknown, status = 200) {
        const calls: { url: string; init: RequestInit | undefined }[] = [];
        const fetchFn: Fetch = async (url, init) => {
            calls.push({ url, init });

            return new Response(status === 204 ? null : JSON.stringify(body), { status });
        };

        return { calls, fetchFn };
    }

    it('should save blocks of the entry as a preset, and replace, rename and delete one, with the CSRF token', async () => {
        const answer = { preset: saved(PROMO, {}) };
        const { calls, fetchFn } = recordingFetch(answer);
        const api = createApi(withEndpoints, fetchFn);

        await expect(api.createPreset?.(['a', 'b'], { name: 'Promo', category: null, description: null })).resolves.toEqual(answer.preset);
        await api.replacePreset?.(PROMO, ['c']);
        await api.updatePreset?.(PROMO, { name: 'Spring promo' });

        expect(calls.map(({ url, init }) => [init?.method, url, init?.body, (init?.headers as Record<string, string>)['X-CSRF-Token']])).toEqual([
            ['POST', '/presets', JSON.stringify({ entry: config.entryUuid, blocks: ['a', 'b'], name: 'Promo', category: null, description: null }), 'abc123'],
            ['PUT', `/presets/${PROMO}`, JSON.stringify({ entry: config.entryUuid, blocks: ['c'] }), 'abc123'],
            ['PATCH', `/presets/${PROMO}`, JSON.stringify({ name: 'Spring promo' }), 'abc123'],
        ]);
        expect(api.presetExportUrl?.(PROMO)).toBe(`/presets/${PROMO}/export`);
    });

    it('should delete an editor preset, and answer with the categories in use with the presets', async () => {
        const deleted = recordingFetch(null, 204);
        await createApi(withEndpoints, deleted.fetchFn).deletePreset?.(PROMO);
        expect(deleted.calls.map(({ url, init }) => [init?.method, url])).toEqual([['DELETE', `/presets/${PROMO}`]]);

        const listed = recordingFetch({ presets: [listing(PROMO)], categories: ['Banners', 42] });
        await expect(createApi(withEndpoints, listed.fetchFn).presets?.(null)).resolves.toEqual({ presets: [listing(PROMO)], startingPoints: [], categories: ['Banners'] });
    });

    it('should have none of them without all their endpoints', () => {
        const api = createApi({ ...withEndpoints, endpoints: { ...withEndpoints.endpoints, presetExport: undefined } });

        expect([api.createPreset, api.replacePreset, api.updatePreset, api.deletePreset, api.presetExportUrl]).toEqual([undefined, undefined, undefined, undefined, undefined]);
    });
});
