import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ApiError, type BuilderApi } from '../src/api';
import type { Change } from '../src/changeSet';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, PresenceResponse, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { block, blockOf, entry, registry, settings, spacer, strings } from './fixtures';

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    endpoints: {
        registry: '/registry',
        entry: '/entry',
        validate: '/validate',
        publish: '/publish',
        discard: '/discard',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
    strings: { 'setono_sylius_cms.builder.loading': 'Loading…' },
};

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi = fakeApi()) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function addBlock(label: string) {
    fireEvent.click(screen.getByRole('button', { name: 'Add block' }));
    fireEvent.click(within(screen.getByRole('dialog', { name: 'Add block' })).getByRole('button', { name: label }));
}

function withBlocks(...blocks: StoredBlock[]): Partial<BuilderApi> {
    return { fetchEntry: async () => ({ entry: entry(blocks), settings: settings(), status: 'never_published', lockToken: 0, editors: [] }) };
}

/** The tree item of the block whose row button has this name. */
function treeItem(name: string, nth = 0): HTMLElement {
    const item = within(screen.getByRole('navigation', { name: 'Blocks' }))
        .getAllByRole('button', { name })
        [nth]?.closest('li');
    if (!(item instanceof HTMLElement)) {
        throw new Error(`The tree has no block "${name}"`);
    }

    return item;
}

/** The draggable row of the block whose row button has this name. */
function row(name: string, nth = 0): HTMLElement {
    const element = treeItem(name, nth).querySelector('[draggable="true"]');
    if (!(element instanceof HTMLElement)) {
        throw new Error(`The block "${name}" has no draggable row`);
    }

    return element;
}

function dataTransfer() {
    return { setData: vi.fn(), getData: vi.fn(() => ''), effectAllowed: 'all', dropEffect: 'none' };
}

describe('the builder', () => {
    it('should show the loading text until the registry and the entry arrive', async () => {
        render(<App config={config} api={fakeApi()} />);

        expect(screen.getByRole('status')).toHaveTextContent('Loading…');
        expect(await screen.findByRole('heading', { level: 1, name: 'About us' })).toBeInTheDocument();
    });

    it('should show the top bar and the block tree of the entry', async () => {
        await openBuilder();

        expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute('href', '/admin/');
        expect(screen.getByText('Never published')).toBeInTheDocument();
        expect(screen.getByRole('status')).toBeEmptyDOMElement();
        expect(screen.getByRole('navigation', { name: 'Blocks' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Heading Welcome', pressed: false })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Settings', selected: true })).toBeInTheDocument();
    });

    it('should add a heading, autosave its text and publish it', async () => {
        const saveChanges = vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 }));
        const publish = vi.fn(async (): Promise<EntryStatus> => 'published');
        await openBuilder(fakeApi({ saveChanges, publish }));

        addBlock('Heading');

        expect(screen.getByRole('button', { name: 'Heading', pressed: true })).toBeInTheDocument();
        fireEvent.change(screen.getByRole('textbox', { name: 'Text' }), { target: { value: 'Hello' } });
        expect(screen.getByRole('status')).toHaveTextContent('Saving…');
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));

        expect(saveChanges).toHaveBeenCalledTimes(1);
        expect(saveChanges.mock.calls[0]?.[0]).toEqual([
            { op: 'add_block', parent: null, index: 1, after: 'a', block: expect.objectContaining({ type: 'heading', version: 1, fields: {} }) },
            { op: 'set_value', key: expect.stringMatching(/^[0-9a-f-]{36}$/), field: 'text', locale: 'en_US', value: 'Hello' },
        ]);
        expect(screen.getByRole('button', { name: 'Heading Hello', pressed: true })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await screen.findByText('Published', { exact: true })).toBeInTheDocument();
        expect(publish).toHaveBeenCalledTimes(1);
    });

    it('should edit the fields of the selected block', async () => {
        await openBuilder();

        fireEvent.click(screen.getByRole('button', { name: 'Heading Welcome' }));

        const fields = screen.getByRole('complementary');
        expect(within(fields).getByRole('textbox', { name: 'Text' })).toHaveValue('Welcome');
        expect(within(fields).getByRole('combobox', { name: 'Level' })).toHaveValue('h2');
    });

    it('should delete a block', async () => {
        await openBuilder();

        fireEvent.click(screen.getByRole('button', { name: 'Delete block' }));

        expect(screen.queryByRole('button', { name: 'Heading Welcome' })).not.toBeInTheDocument();
        expect(screen.getByText('No blocks yet')).toBeInTheDocument();
    });

    it('should report a failed save and a failed publish', async () => {
        const api = fakeApi({
            saveChanges: vi.fn(() => Promise.reject(new Error('Offline'))),
            publish: vi.fn(() => Promise.reject(new Error('Offline'))),
        });
        await openBuilder(api);

        fireEvent.click(screen.getByRole('button', { name: 'Delete block' }));

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Couldn't save"));

        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await screen.findByRole('alert')).toHaveTextContent("Couldn't publish");
        expect(api.publish).not.toHaveBeenCalled();
    });

    it('should show unpublished changes once a published entry is saved, and discard them', async () => {
        const api = fakeApi({
            fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
            saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'unpublished_changes', lockToken: 1 })),
        });
        await openBuilder(api);

        expect(screen.getByText('Published', { exact: true })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Discard changes' })).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Heading Welcome' }));
        fireEvent.change(screen.getByRole('textbox', { name: 'Text' }), { target: { value: 'Welcome back' } });

        expect(await screen.findByText('Unpublished changes', { exact: true })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Discard changes' }));

        expect(await screen.findByRole('button', { name: 'Heading Welcome' })).toBeInTheDocument();
        expect(screen.getByRole('textbox', { name: 'Text' })).toHaveValue('Welcome');
        expect(screen.getByText('Published', { exact: true })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Discard changes' })).not.toBeInTheDocument();
        expect(api.discard).toHaveBeenCalledTimes(1);
    });

    it('should report a failed discard', async () => {
        await openBuilder(
            fakeApi({
                fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a')]), settings: settings(), status: 'unpublished_changes', lockToken: 0, editors: [] })),
                discard: vi.fn(() => Promise.reject(new Error('Offline'))),
            }),
        );

        fireEvent.click(screen.getByRole('button', { name: 'Discard changes' }));

        expect(await screen.findByRole('alert')).toHaveTextContent("Couldn't discard the changes");
        expect(screen.getByText('Unpublished changes', { exact: true })).toBeInTheDocument();
    });

    it('should show why the server refused a publish', async () => {
        await openBuilder(fakeApi({ publish: vi.fn(() => Promise.reject(new ApiError('Not on a Sunday', 422))) }));

        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await screen.findByRole('alert')).toHaveTextContent("Couldn't publish: Not on a Sunday");
        expect(screen.getByText('Never published', { exact: true })).toBeInTheDocument();
    });

    it('should say so when the entry cannot be loaded', async () => {
        render(<App config={config} api={fakeApi({ fetchEntry: () => Promise.reject(new Error('Not found')) })} />);

        expect(await screen.findByRole('alert')).toHaveTextContent('setono_sylius_cms.builder.load_failed');
    });

    it('should take its UI text from the registry strings', async () => {
        await openBuilder(fakeApi({ fetchRegistry: async () => ({ ...registry, strings: { ...strings, 'setono_sylius_cms.builder.add_block': 'Tilføj blok' } }) }));

        expect(screen.getByRole('button', { name: 'Tilføj blok' })).toBeInTheDocument();
    });
});

describe('the block tree and the block picker', () => {
    const twoColumns = () => blockOf('columns', 'cols', [blockOf('column', 'c1', [block('h', { text: { en_US: 'Inside' } })]), blockOf('column', 'c2')]);

    it('should show the blocks inside a block nested under it', async () => {
        await openBuilder(fakeApi(withBlocks(twoColumns())));

        const columns = treeItem('Columns');
        expect(within(columns).getAllByRole('button', { name: 'Column' })).toHaveLength(2);
        expect(within(treeItem('Column')).getByRole('button', { name: 'Heading Inside' })).toBeInTheDocument();
    });

    it('should list only the block types allowed at the spot, each with its thumbnail, and search them', async () => {
        await openBuilder();

        fireEvent.click(screen.getByRole('button', { name: 'Add block' }));

        const picker = screen.getByRole('dialog', { name: 'Add block' });
        expect(within(picker).getAllByRole('button').map((button) => button.textContent)).toEqual(['Group', 'Columns', 'Spacer', 'Heading']);
        expect(within(picker).getByRole('button', { name: 'Columns' }).querySelector('img')).toHaveAttribute('src', '/thumbnail/columns.svg');

        fireEvent.change(within(picker).getByRole('searchbox', { name: 'Search block types' }), { target: { value: 'COL' } });
        expect(within(picker).getAllByRole('button').map((button) => button.textContent)).toEqual(['Columns']);

        fireEvent.change(within(picker).getByRole('searchbox', { name: 'Search block types' }), { target: { value: 'carousel' } });
        expect(within(picker).queryAllByRole('button')).toEqual([]);
        expect(within(picker).getByText('No block types match')).toBeInTheDocument();
    });

    it('should insert Columns with its two Columns straight away', async () => {
        await openBuilder();

        addBlock('Columns');

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(within(treeItem('Columns')).getAllByRole('button', { name: 'Column' })).toHaveLength(2);
    });

    it('should add a Column straight away from + inside Columns instead of opening the picker', async () => {
        await openBuilder(fakeApi(withBlocks(twoColumns())));

        fireEvent.click(within(treeItem('Columns')).getByRole('button', { name: 'Add a block inside Columns' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(within(treeItem('Columns')).getAllByRole('button', { name: 'Column' })).toHaveLength(3);
    });

    it('should open the picker from + inside a block and insert into its children', async () => {
        await openBuilder(fakeApi(withBlocks(blockOf('group', 'g'))));

        fireEvent.click(within(treeItem('Group')).getByRole('button', { name: 'Add a block inside Group' }));
        fireEvent.click(within(screen.getByRole('dialog', { name: 'Add block' })).getByRole('button', { name: 'Heading' }));

        expect(within(treeItem('Group')).getByRole('button', { name: 'Heading', pressed: true })).toBeInTheDocument();
    });

    it('should leave a disabled block type out of the picker and keep its blocks in the tree', async () => {
        const disabled = { ...registry, blockTypes: registry.blockTypes.map((type) => (type === spacer ? { ...spacer, disabled: true } : type)) };
        await openBuilder(fakeApi({ fetchRegistry: async () => disabled, ...withBlocks(blockOf('spacer', 's')) }));

        expect(treeItem('Spacer')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Add block' }));

        expect(within(screen.getByRole('dialog', { name: 'Add block' })).queryByRole('button', { name: 'Spacer' })).not.toBeInTheDocument();
    });

    it("should show the selected block's children with + to add one", async () => {
        await openBuilder(fakeApi(withBlocks(blockOf('group', 'g', [block('h', { text: { en_US: 'Inside' } })]))));

        fireEvent.click(screen.getByRole('button', { name: 'Group' }));

        const selection = screen.getByRole('complementary');
        expect(within(selection).getByRole('heading', { name: 'Blocks inside' })).toBeInTheDocument();
        fireEvent.click(within(selection).getByRole('button', { name: 'Heading Inside' }));
        expect(within(selection).getByRole('heading', { name: 'Heading' })).toBeInTheDocument();
    });

    it('should nest a block dropped onto another that may hold it', async () => {
        await openBuilder(fakeApi(withBlocks(block('top', { text: { en_US: 'Moved' } }), twoColumns())));
        const transfer = dataTransfer();

        fireEvent.dragStart(row('Heading Moved'), { dataTransfer: transfer });
        fireEvent.dragOver(row('Column', 1), { dataTransfer: transfer, clientY: 0 });
        fireEvent.drop(row('Column', 1), { dataTransfer: transfer });
        fireEvent.dragEnd(row('Heading Moved'), { dataTransfer: transfer });

        expect(within(treeItem('Column', 1)).getByRole('button', { name: 'Heading Moved' })).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('should refuse a drop the rules do not allow and say so', async () => {
        await openBuilder(fakeApi(withBlocks(block('top', { text: { en_US: 'Top' } }), twoColumns())));
        const transfer = dataTransfer();

        fireEvent.dragStart(row('Column'), { dataTransfer: transfer });
        // Onto the top-level heading, which holds no blocks, so the Column would land beside it at the top level
        const accepted = fireEvent.dragOver(row('Heading Top'), { dataTransfer: transfer, clientY: 0 });
        // A browser leaves the spot towards nothing just before a refused drag ends
        fireEvent.dragLeave(row('Heading Top'), { dataTransfer: transfer, relatedTarget: null });
        fireEvent.dragEnd(row('Column'), { dataTransfer: transfer });

        expect(accepted).toBe(true);
        expect(screen.getByRole('alert')).toHaveTextContent("Column can't go there");
        expect(within(treeItem('Columns')).getAllByRole('button', { name: 'Column' })).toHaveLength(2);
    });
});
