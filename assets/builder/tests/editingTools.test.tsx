import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import type { Change } from '../src/changeSet';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, PresenceResponse, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { block, blockOf, entry, registry, settings } from './fixtures';

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
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

function fakeApi(blocks: StoredBlock[]) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    } satisfies BuilderApi;
}

async function openBuilder(...blocks: StoredBlock[]) {
    const api = fakeApi(blocks);
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });

    return api;
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

/** The names of the tree's rows, in order. */
function rows(): string[] {
    return [...tree().querySelectorAll('li button[aria-pressed]')].map((button) => button.textContent ?? '');
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

function topBarButton(name: string): HTMLElement {
    return within(screen.getByRole('banner')).getByRole('button', { name });
}

describe('undo and redo', () => {
    it('should undo and redo from the top bar, and save each like any other change', async () => {
        const api = await openBuilder(block('a', { text: { en_US: 'Welcome' } }), block('b', { text: { en_US: 'Goodbye' } }));
        expect(topBarButton('Undo')).toBeDisabled();
        expect(topBarButton('Redo')).toBeDisabled();

        fireEvent.click(within(tree()).getAllByRole('button', { name: 'Delete block' })[0] as HTMLElement);
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledTimes(1));
        expect(rows()).toEqual(['HeadingGoodbye']);

        fireEvent.click(topBarButton('Undo'));

        expect(rows()).toEqual(['HeadingWelcome', 'HeadingGoodbye']);
        expect(topBarButton('Undo')).toBeDisabled();
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledTimes(2));
        expect(api.saveChanges.mock.calls[1]?.[0]).toEqual([{ op: 'add_block', parent: null, index: 0, block: block('a', { text: { en_US: 'Welcome' } }) }]);

        fireEvent.click(topBarButton('Redo'));

        expect(rows()).toEqual(['HeadingGoodbye']);
        expect(topBarButton('Redo')).toBeDisabled();
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledTimes(3));
        expect(api.saveChanges.mock.calls[2]?.[0]).toEqual([{ op: 'remove_block', key: 'a' }]);
    });

    it.each([
        ['⌘Z and ⇧⌘Z', { metaKey: true }],
        ['Ctrl+Z and Ctrl+Shift+Z', { ctrlKey: true }],
    ])('should undo and redo with %s', async (_, modifier) => {
        await openBuilder(block('a', { text: { en_US: 'Welcome' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Delete block' }));

        fireEvent.keyDown(document.body, { key: 'z', ...modifier });
        expect(rows()).toEqual(['HeadingWelcome']);

        fireEvent.keyDown(document.body, { key: 'Z', shiftKey: true, ...modifier });
        expect(rows()).toEqual([]);
    });

    it('should undo typing in a field in one step, with the field showing the value it had', async () => {
        await openBuilder(block('a', { text: { en_US: 'Welcome' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Welcome' }));
        const text = within(selectionPane()).getByRole('textbox', { name: 'Text' });

        for (const value of ['Welcome b', 'Welcome ba', 'Welcome back']) {
            fireEvent.change(text, { target: { value } });
        }
        fireEvent.keyDown(text, { key: 'z', metaKey: true });

        expect(text).toHaveValue('Welcome');
    });

    it('should leave a key press without ⌘ or Ctrl alone', async () => {
        await openBuilder(block('a', { text: { en_US: 'Welcome' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Delete block' }));

        fireEvent.keyDown(document.body, { key: 'z' });

        expect(rows()).toEqual([]);
    });
});

describe('selecting several blocks', () => {
    it.each([
        ['⌘-click', { metaKey: true }],
        ['Ctrl-click', { ctrlKey: true }],
    ])('should select several blocks with %s, and delete them together', async (_, modifier) => {
        await openBuilder(block('a', { text: { en_US: 'First' } }), block('b', { text: { en_US: 'Second' } }), block('c', { text: { en_US: 'Third' } }));

        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading First' }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Third' }), modifier);

        expect(within(tree()).getByRole('button', { name: 'Heading First' })).toHaveAttribute('aria-pressed', 'true');
        expect(within(tree()).getByRole('button', { name: 'Heading Second' })).toHaveAttribute('aria-pressed', 'false');
        expect(within(tree()).getByRole('button', { name: 'Heading Third' })).toHaveAttribute('aria-pressed', 'true');
        expect(within(selectionPane()).getByRole('heading', { name: '2 blocks selected' })).toBeInTheDocument();

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Delete block' }));

        expect(rows()).toEqual(['HeadingSecond']);
    });

    it('should take a block out of the selection with another ⌘-click', async () => {
        await openBuilder(block('a', { text: { en_US: 'First' } }), block('b', { text: { en_US: 'Second' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading First' }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Second' }), { metaKey: true });

        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Second' }), { metaKey: true });

        expect(within(tree()).getByRole('button', { name: 'Heading Second' })).toHaveAttribute('aria-pressed', 'false');
        expect(within(selectionPane()).getByRole('textbox', { name: 'Text' })).toHaveValue('First');
    });

    it('should delete the whole selection from the delete button of a selected row', async () => {
        await openBuilder(block('a', { text: { en_US: 'First' } }), block('b', { text: { en_US: 'Second' } }), block('c', { text: { en_US: 'Third' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading First' }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Second' }), { metaKey: true });

        fireEvent.click(within(tree()).getAllByRole('button', { name: 'Delete block' })[1] as HTMLElement);

        expect(rows()).toEqual(['HeadingThird']);
    });

    it('should move a dragged selection together', async () => {
        await openBuilder(block('a', { text: { en_US: 'First' } }), blockOf('group', 'g'), block('b', { text: { en_US: 'Second' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading First' }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Second' }), { metaKey: true });
        const transfer = { setData: vi.fn(), getData: vi.fn(() => ''), effectAllowed: 'all', dropEffect: 'none' };
        const row = (name: string) => within(tree()).getByRole('button', { name }).closest('[draggable="true"]') as HTMLElement;

        fireEvent.dragStart(row('Heading Second'), { dataTransfer: transfer });
        // The middle of the Group row drops inside it
        vi.spyOn(row('Group'), 'getBoundingClientRect').mockReturnValue(new DOMRect(0, 0, 200, 40));
        fireEvent.dragOver(row('Group'), { dataTransfer: transfer, clientY: 20 });
        fireEvent.drop(row('Group'), { dataTransfer: transfer });
        fireEvent.dragEnd(row('Heading Second'), { dataTransfer: transfer });

        const group = within(tree()).getByRole('button', { name: 'Group' }).closest('li') as HTMLElement;
        expect(within(group).getAllByRole('button', { name: /^Heading/ }).map((button) => button.textContent)).toEqual(['HeadingFirst', 'HeadingSecond']);
    });
});

describe('the selected block in the right pane', () => {
    const nested = () => blockOf('group', 'g', [blockOf('columns', 'cols', [blockOf('column', 'c1', [block('h', { text: { en_US: 'Deep' } })]), blockOf('column', 'c2')])]);

    it("should show the block's path in the tree, each step selectable", async () => {
        await openBuilder(nested());
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Deep' }));

        const path = within(selectionPane()).getByRole('list', { name: 'Path' });
        expect(within(path).getAllByRole('listitem').map((step) => step.textContent)).toEqual(['Group', 'Columns', 'Column', 'Heading']);
        expect(within(path).getByText('Heading')).toHaveAttribute('aria-current', 'location');

        fireEvent.click(within(path).getByRole('button', { name: 'Columns' }));

        expect(within(tree()).getByRole('button', { name: 'Columns' })).toHaveAttribute('aria-pressed', 'true');
        expect(within(selectionPane()).getByRole('heading', { name: 'Columns' })).toBeInTheDocument();
    });

    it('should duplicate the selected block, with the blocks inside it, and select the copy', async () => {
        await openBuilder(blockOf('group', 'g', [block('h', { text: { en_US: 'Inside' } })]));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Group' }));

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Duplicate' }));

        expect(rows()).toEqual(['Group', 'HeadingInside', 'Group', 'HeadingInside']);
        expect(within(tree()).getAllByRole('button', { name: 'Group' }).map((button) => button.getAttribute('aria-pressed'))).toEqual(['false', 'true']);
    });

    it('should move the selected block up and down, but not past the ends of its list', async () => {
        await openBuilder(block('a', { text: { en_US: 'First' } }), block('b', { text: { en_US: 'Second' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Second' }));
        expect(within(selectionPane()).getByRole('button', { name: 'Move down' })).toBeDisabled();

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Move up' }));

        expect(rows()).toEqual(['HeadingSecond', 'HeadingFirst']);
        expect(within(selectionPane()).getByRole('button', { name: 'Move up' })).toBeDisabled();

        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Move down' }));

        expect(rows()).toEqual(['HeadingFirst', 'HeadingSecond']);
    });

    it('should refuse a duplicate the nesting rules have no room for', async () => {
        const full = blockOf('columns', 'cols', ['c1', 'c2', 'c3', 'c4'].map((key) => blockOf('column', key)));
        await openBuilder(full);
        fireEvent.click(within(tree()).getAllByRole('button', { name: 'Column' })[0] as HTMLElement);

        expect(within(selectionPane()).getByRole('button', { name: 'Duplicate' })).toBeDisabled();
    });
});
