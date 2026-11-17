import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { Change } from '../src/changeSet';
import type { BuilderConfig } from '../src/config';
import type { AllowedBlockType, BlockTypeDefinition, ContentTypeDefinition, EntryResponse, EntryStatus, PresenceResponse, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { block, blockOf, blockTypes, entry, fakeSearch, page, registry, settings, shape, storeWith } from './fixtures';

/** The Snippet block, as the registry exports it. */
export const snippetType: BlockTypeDefinition = {
    code: 'snippet',
    label: 'Snippet',
    version: 1,
    thumbnail: '/thumbnail/snippet.svg',
    disabled: false,
    parents: null,
    children: [],
    styles: [],
    fields: [
        {
            name: 'snippet',
            schema: { type: 'string', format: 'uuid', 'x-cms-type': 'snippet', 'x-cms-label': 'Snippet', 'x-cms-help': null, 'x-cms-required': true, 'x-cms-translatable': false },
        },
    ],
};

const FOOTER = '0193a5be-0000-7000-8000-00000000f007';

const anywhere: AllowedBlockType[] = ['group', 'columns', 'spacer', 'heading', 'snippet'].map((type) => ({ type, min: null, max: null }));
const withSnippets: ContentTypeDefinition = { ...page, blocks: anywhere };
const types: BlockTypeDefinition[] = [...blockTypes.map((type) => (type.code === 'group' ? { ...type, children: anywhere } : type)), snippetType];

function shows(key: string, snippet: string = FOOTER) {
    return blockOf('snippet', key, [], { snippet });
}

describe('detaching a Snippet block', () => {
    it('should replace the block with a copy of the snippet\'s blocks, with new keys for them and every block inside them, and select the first', () => {
        const store = storeWith([block('a'), shows('s'), block('z')], types, withSnippets);
        const footer = [block('f1', { text: { en_US: 'Free shipping' } }), blockOf('group', 'f2', [block('f3')])];

        expect(store.getState().detachSnippet('s', footer)).toEqual(['new-1', 'new-2']);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a', 'new-1', 'new-2', ['new-3'], 'z']);
        expect(store.getState().entry.tree.blocks[1]).toEqual({ ...block('f1', { text: { en_US: 'Free shipping' } }), key: 'new-1' });
        expect(store.getState().selection).toEqual(['new-1']);
        expect(store.getState().selectedKey).toBe('new-1');
        // What it copied is left as it was
        expect(shape(footer)).toEqual(['f1', 'f2', ['f3']]);
    });

    it('should detach a Snippet block inside another block in place', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), shows('s')])], types, withSnippets);

        store.getState().detachSnippet('s', [block('f1')]);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['g', ['a', 'new-1']]);
    });

    it('should save it as removing the Snippet block and adding the copies, and undo it in one step', () => {
        const store = storeWith([shows('s')], types, withSnippets);

        store.getState().detachSnippet('s', [block('f1'), block('f2')]);

        expect(store.getState().unsaved).toEqual([
            { op: 'remove_block', key: 's' },
            { op: 'add_block', parent: null, index: 0, block: { ...block('f1'), key: 'new-1' } },
            { op: 'add_block', parent: null, index: 1, after: 'new-1', block: { ...block('f2'), key: 'new-2' } },
        ]);

        store.getState().undo();

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['s']);
    });

    it('should refuse to detach where the block\'s spot can\'t hold the snippet\'s blocks', () => {
        const onlySnippets: ContentTypeDefinition = { ...page, blocks: [{ type: 'snippet', min: null, max: null }, { type: 'heading', min: null, max: 2 }] };
        const store = storeWith([block('a'), shows('s')], types, onlySnippets);

        expect(store.getState().detachSnippet('s', [blockOf('spacer', 'f1')])).toBeNull();
        expect(store.getState().detachSnippet('s', [block('f1'), block('f2')])).toBeNull();
        expect(store.getState().detachSnippet('missing', [block('f1')])).toBeNull();

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a', 's']);
        expect(store.getState().history.done).toHaveLength(0);
    });

    it('should leave nothing in the block\'s place when the snippet shows no blocks', () => {
        const store = storeWith([block('a'), shows('s')], types, withSnippets);

        expect(store.getState().detachSnippet('s', [])).toEqual([]);

        expect(shape(store.getState().entry.tree.blocks)).toEqual(['a']);
        expect(store.getState().selection).toEqual([]);
    });
});

const ENTRY = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

const snippetStrings: Record<string, string> = {
    'setono_sylius_cms.builder.items.change': 'Change…',
    'setono_sylius_cms.builder.items.choose': 'Choose…',
    'setono_sylius_cms.builder.items.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.items.remove': 'Remove',
    'setono_sylius_cms.builder.items.not_found': '%code% no longer exists, so it isn’t shown.',
    'setono_sylius_cms.builder.snippet.shared': 'Shared',
    'setono_sylius_cms.builder.snippet.shared_help': 'It shows a snippet, which stays the same wherever it’s used.',
    'setono_sylius_cms.builder.snippet.edit': 'Edit snippet',
    'setono_sylius_cms.builder.snippet.detach': 'Detach to edit here',
    'setono_sylius_cms.builder.snippet.detach_failed': 'The snippet couldn’t be detached. Try again.',
    'setono_sylius_cms.builder.snippet.search': 'Search snippets',
};

const config: BuilderConfig = {
    entryUuid: ENTRY,
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    overviewUrl: `/admin/cms/entry/${ENTRY}`,
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
        builder: '/admin/cms/entry/00000000-0000-4000-8000-000000000000/builder',
        snippetBlocks: '/admin/cms/api/snippets/00000000-0000-4000-8000-000000000000/blocks',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

function fakeApi(blocks: StoredBlock[], snippetBlocks: StoredBlock[] = []) {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, blockTypes: types, contentTypes: [withSnippets], strings: { ...registry.strings, ...snippetStrings } })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        snippetBlocks: vi.fn(async (_snippet: string): Promise<StoredBlock[]> => snippetBlocks),
    } satisfies BuilderApi;
}

async function openBuilder(blocks: StoredBlock[], snippetBlocks: StoredBlock[] = []) {
    const api = fakeApi(blocks, snippetBlocks);
    const search = fakeSearch({ snippet: [{ ref: FOOTER, name: 'Footer', thumbnail: null }] });
    const navigate = vi.fn();
    render(<App config={config} api={api} autosaveDelay={10} searchApi={search} navigate={navigate} />);
    await screen.findByRole('heading', { level: 1 });

    return { api, search, navigate };
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

function selectRow(name: RegExp): void {
    fireEvent.click(within(tree()).getByRole('button', { name, pressed: false }));
}

describe('a Snippet block in the builder', () => {
    it('should be marked as shared in the tree, named by the snippet it shows', async () => {
        await openBuilder([block('a', { text: { en_US: 'Welcome' } }), shows('s')]);

        const row = await within(tree()).findByRole('button', { name: /Snippet.*Footer/ });
        expect(row.closest('div')).toHaveTextContent('Shared');
    });

    it('should search only the snippets whose declared context the entry provides, leaving out the entry itself', async () => {
        const { api, search } = await openBuilder([blockOf('snippet', 's')]);
        selectRow(/Snippet/);

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Snippet' }));
        fireEvent.click(await screen.findByRole('option', { name: 'Footer' }));

        expect(search.search).toHaveBeenCalledWith('snippet', '', { provides: 'entry', entry: ENTRY });
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalled());
        expect(api.saveChanges.mock.calls[0]?.[0]).toEqual([{ op: 'set_value', key: 's', field: 'snippet', locale: null, value: FOOTER }]);
    });

    it('should open the snippet in the builder with a way back to the block, once the changes are sent', async () => {
        const { navigate } = await openBuilder([shows('s')]);
        selectRow(/Snippet/);

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Edit snippet' }));

        await waitFor(() => expect(navigate).toHaveBeenCalledTimes(1));
        const url = new URL(String(navigate.mock.calls[0]?.[0]), 'http://admin.example');
        expect(url.pathname).toBe(`/admin/cms/entry/${FOOTER}/builder`);
        const back = new URL(url.searchParams.get('back') ?? '', 'http://admin.example');
        expect(back.pathname).toBe(window.location.pathname);
        expect(back.searchParams.get('block')).toBe('s');
    });

    it('should detach the block into a copy of the snippet\'s blocks, and select the first copy', async () => {
        const { api } = await openBuilder([shows('s')], [block('f1', { text: { en_US: 'Free shipping' } })]);
        selectRow(/Snippet/);

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Detach to edit here' }));

        expect(await within(tree()).findByRole('button', { name: /Heading.*Free shipping/, pressed: true })).toBeInTheDocument();
        expect(within(tree()).queryByRole('button', { name: /Snippet/ })).not.toBeInTheDocument();
        expect(api.snippetBlocks).toHaveBeenCalledWith(FOOTER);
        expect(within(selectionPane()).getByRole('heading', { level: 2 })).toHaveTextContent('Heading');
    });
});
