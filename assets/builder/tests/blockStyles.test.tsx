import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { Change } from '../src/changeSet';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, StoredBlock } from '../src/types';
import { block, blockOf, entry, registry, settings, storeWith } from './fixtures';

describe('picking a block style', () => {
    it("should store the style's code in the block's style, and null for none", () => {
        const store = storeWith([blockOf('group', 'g')]);

        store.getState().setBlockStyle('g', 'dark');
        expect(store.getState().entry.tree.blocks[0]?.style).toBe('dark');

        store.getState().setBlockStyle('g', null);
        expect(store.getState().entry.tree.blocks[0]?.style).toBeNull();
    });

    it('should save the last style picked for a block as one set_style change', () => {
        const store = storeWith([blockOf('group', 'g'), blockOf('group', 'h')]);

        store.getState().setBlockStyle('g', 'muted');
        store.getState().setBlockStyle('h', 'muted');
        store.getState().setBlockStyle('g', 'dark');

        expect(store.getState().unsaved).toEqual<Change[]>([
            { op: 'set_style', key: 'h', style: 'muted' },
            { op: 'set_style', key: 'g', style: 'dark' },
        ]);
    });

    it('should undo and redo a style picked, each saved like any other change', () => {
        const store = storeWith([{ ...blockOf('group', 'g'), style: 'muted' }]);

        store.getState().setBlockStyle('g', 'dark');
        store.getState().setBlockStyle('g', null);

        store.getState().undo();
        expect(store.getState().entry.tree.blocks[0]?.style).toBe('dark');
        store.getState().undo();
        expect(store.getState().entry.tree.blocks[0]?.style).toBe('muted');
        expect(store.getState().unsaved).toEqual<Change[]>([{ op: 'set_style', key: 'g', style: 'muted' }]);

        store.getState().redo();
        expect(store.getState().entry.tree.blocks[0]?.style).toBe('dark');
        expect(store.getState().unsaved).toEqual<Change[]>([{ op: 'set_style', key: 'g', style: 'dark' }]);
    });

    it('should not record picking the style a block already has', () => {
        const store = storeWith([{ ...blockOf('group', 'g'), style: 'dark' }]);

        store.getState().setBlockStyle('g', 'dark');

        expect(store.getState().history.done).toHaveLength(0);
        expect(store.getState().unsaved).toEqual([]);
    });

    it('should drop the style picked for a saved block that gets deleted', () => {
        const store = storeWith([blockOf('group', 'g')]);

        store.getState().setBlockStyle('g', 'dark');
        store.getState().removeBlock('g');

        expect(store.getState().unsaved).toEqual<Change[]>([{ op: 'remove_block', key: 'g' }]);
    });

    it('should send the style picked for a new block after the block itself', () => {
        const store = storeWith();

        const key = store.getState().addBlock('group') ?? '';
        store.getState().setBlockStyle(key, 'dark');

        expect(store.getState().unsaved.map((change) => change.op)).toEqual(['add_block', 'set_style']);
        expect(store.getState().unsaved[1]).toEqual({ op: 'set_style', key, style: 'dark' });
    });

    it('should send the style of a block put back by undo with the block', () => {
        const store = storeWith([{ ...blockOf('group', 'g'), style: 'dark' }, block('a')]);

        store.getState().removeBlock('g');
        store.getState().undo();

        expect(store.getState().unsaved).toEqual<Change[]>([
            { op: 'remove_block', key: 'g' },
            { op: 'add_block', parent: null, index: 0, block: { ...blockOf('group', 'g'), style: 'dark' } },
        ]);
    });
});

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    endpoints: {
        registry: '/registry',
        entry: '/entry',
        publish: '/publish',
        discard: '/discard',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

async function openBuilder(...blocks: StoredBlock[]) {
    const api = {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'never_published' })),
        saveChanges: vi.fn(async (_changes: readonly Change[]): Promise<EntryStatus> => 'never_published'),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'published' })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
    } satisfies BuilderApi;
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });

    return api;
}

function select(name: string): void {
    fireEvent.click(within(screen.getByRole('navigation', { name: 'Blocks' })).getByRole('button', { name }));
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

describe('the style picker in the right pane', () => {
    it("should offer the block type's styles and none, and show the block's", async () => {
        await openBuilder({ ...blockOf('group', 'g'), style: 'muted' });
        select('Group');

        const picker = within(selectionPane()).getByRole('combobox', { name: 'Style' });

        expect([...picker.querySelectorAll('option')].map((option) => option.textContent)).toEqual(['None', 'Muted', 'Dark']);
        expect(picker).toHaveValue('muted');
    });

    it('should autosave the style picked, and none', async () => {
        const api = await openBuilder(blockOf('group', 'g'));
        select('Group');
        const picker = within(selectionPane()).getByRole('combobox', { name: 'Style' });

        fireEvent.change(picker, { target: { value: 'dark' } });

        expect(picker).toHaveValue('dark');
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_style', key: 'g', style: 'dark' }]));

        fireEvent.change(picker, { target: { value: '' } });

        expect(picker).toHaveValue('');
        await waitFor(() => expect(api.saveChanges).toHaveBeenLastCalledWith([{ op: 'set_style', key: 'g', style: null }]));
    });

    it('should show none for a style that is no longer declared', async () => {
        await openBuilder({ ...blockOf('group', 'g'), style: 'retired' });
        select('Group');

        expect(within(selectionPane()).getByRole('combobox', { name: 'Style' })).toHaveValue('');
    });

    it('should have no style picker for a block type without styles', async () => {
        await openBuilder(block('a', { text: { en_US: 'Hello' } }));
        select('Heading Hello');

        expect(within(selectionPane()).queryByRole('combobox', { name: 'Style' })).not.toBeInTheDocument();
    });
});
