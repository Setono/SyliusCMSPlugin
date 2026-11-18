import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi, type Mock } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import { envelope, openEnvelope, type FromBridge, type ToBridge } from '../src/bridge/protocol';
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
        schedule: '/schedule',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

const ORIGIN = 'http://admin.example';

/** Renders each block as a heading with its key, the way preview_render answers. */
function renderedBlocks() {
    return vi.fn(async (_token: string, blocks: readonly StoredBlock[]) =>
        Object.fromEntries(blocks.map(({ key }) => [key, `<!--cms:block ${key} heading--><h2>${key}</h2><!--/cms:block ${key}-->`])),
    );
}

function fakeApi(blocks: StoredBlock[] = [block('a', { text: { en_US: 'Welcome' } }), block('b', { text: { en_US: 'Goodbye' } })], overrides: Partial<BuilderApi> = {}) {
    let count = 0;

    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async ({ locale }: PreviewRequest) => `${ORIGIN}/${locale}/about-us?_cms_preview=token-${++count}`),
        renderPreview: renderedBlocks(),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

function frame(): HTMLIFrameElement {
    return screen.getByTitle('Preview') as HTMLIFrameElement;
}

/** Opens the builder and has the bridge in the preview say it's ready. Answers what the builder sends the bridge. */
async function openWithBridge(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
    await waitFor(() => expect(frame()).toHaveAttribute('src'));

    return connectBridge();
}

/**
 * Has the bridge in the preview say it's ready. The preview listens to the bridge from an effect that runs once the
 * iframe is in place, which React may not have run yet when the iframe's src shows, so the effects and the promises
 * pending are let run first: otherwise "ready" can come before anyone listens, and the builder never answers it.
 */
async function connectBridge() {
    await act(async () => {
        await Promise.resolve();
    });

    const contentWindow = frame().contentWindow as Window;
    const postMessage = vi.spyOn(contentWindow, 'postMessage').mockImplementation(() => undefined);
    fromBridge({ type: 'ready' });

    return {
        postMessage,
        /** The messages sent to the bridge so far. */
        sent: (): ToBridge[] => postMessage.mock.calls.map(([data]) => openEnvelope<ToBridge>(data)).filter((message): message is ToBridge => message !== null),
    };
}

function fromBridge(message: FromBridge, origin = ORIGIN) {
    act(() => {
        window.dispatchEvent(new MessageEvent('message', { data: envelope(message), origin, source: frame().contentWindow }));
    });
}

/** Picks a block type in the block picker. */
function pick(label: string) {
    fireEvent.click(within(screen.getByRole('dialog', { name: 'Add block' })).getByRole('button', { name: label }));
}

function treeRow(name: string): HTMLElement {
    return within(screen.getByRole('navigation')).getByRole('button', { name: new RegExp(name) });
}

describe('the bridge in the preview', () => {
    it('should set the bridge up once it is ready, with its strings, at the origin of the preview', async () => {
        const { postMessage, sent } = await openWithBridge(fakeApi());

        expect(sent()).toEqual([
            {
                type: 'init',
                strings: {
                    selectParent: 'Select parent',
                    settings: 'Settings',
                    delete: 'Delete block',
                    addBefore: 'Add block above',
                    addAfter: 'Add block below',
                    moveUp: 'Move up',
                    moveDown: 'Move down',
                    duplicate: 'Duplicate',
                    formatting: 'Formatting',
                    bold: 'Bold',
                    italic: 'Italic',
                    underline: 'Underline',
                    strike: 'Strikethrough',
                    code: 'Code',
                },
                readOnly: false,
            },
            { type: 'select', key: null },
        ]);
        expect(postMessage).toHaveBeenCalledWith(expect.anything(), ORIGIN);
    });

    it('should ignore messages from anywhere but the preview', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fromBridge({ type: 'select', key: 'a' }, 'http://elsewhere.example');
        act(() => {
            window.dispatchEvent(new MessageEvent('message', { data: envelope({ type: 'select', key: 'a' }), origin: ORIGIN, source: window }));
        });

        expect(treeRow('Welcome')).toHaveAttribute('aria-pressed', 'false');
        expect(sent()).toHaveLength(2);
    });

    it('should select in the tree and the right pane the block clicked in the preview', async () => {
        await openWithBridge(fakeApi());

        fromBridge({ type: 'select', key: 'b' });

        expect(treeRow('Goodbye')).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByLabelText('Text')).toHaveValue('Goodbye');
    });

    it('should flag in the tree the blocks the bridge says have a link that does not resolve in the page, until it says otherwise', async () => {
        await openWithBridge(fakeApi());
        const flag = { name: "A link here isn't shown in the preview's channel and locale" };

        fromBridge({ type: 'unresolved_links', keys: ['b'] });

        expect(within(treeRow('Goodbye').parentElement as HTMLElement).getByRole('img', flag)).toBeInTheDocument();
        expect(within(treeRow('Welcome').parentElement as HTMLElement).queryByRole('img', flag)).not.toBeInTheDocument();

        fromBridge({ type: 'unresolved_links', keys: [] });

        expect(within(treeRow('Goodbye').parentElement as HTMLElement).queryByRole('img', flag)).not.toBeInTheDocument();
    });

    it('should drop the flags of links that do not resolve when the preview loads another page', async () => {
        const api = fakeApi();
        await openWithBridge(api);
        fromBridge({ type: 'unresolved_links', keys: ['a'] });

        fireEvent.change(screen.getByRole('combobox', { name: 'Shown to' }), { target: { value: '@no_group' } });

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(2));
        expect(within(treeRow('Welcome').parentElement as HTMLElement).queryByRole('img', { name: "A link here isn't shown in the preview's channel and locale" })).not.toBeInTheDocument();
    });

    it('should outline in the preview the block selected in the tree', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fireEvent.click(treeRow('Goodbye'));

        expect(sent().at(-1)).toEqual({ type: 'select', key: 'b' });
    });

    it('should re-render a changed block under the preview token and patch it in, without a reload', async () => {
        const api = fakeApi();
        const { sent } = await openWithBridge(api);
        fireEvent.click(treeRow('Welcome'));

        fireEvent.change(screen.getByLabelText('Text'), { target: { value: 'Welcome back' } });

        await waitFor(() => expect(sent().at(-1)).toEqual({ type: 'patch', blocks: [{ key: 'a', html: '<!--cms:block a heading--><h2>a</h2><!--/cms:block a-->' }] }));
        expect(api.renderPreview).toHaveBeenCalledExactlyOnceWith('token-1', [block('a', { text: { en_US: 'Welcome back' } })]);

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);
    });

    it('should re-render a block whose style was picked and patch it in, without a reload', async () => {
        const api = fakeApi([blockOf('group', 'g', [block('a', { text: { en_US: 'Inside' } })])]);
        const { sent } = await openWithBridge(api);
        fireEvent.click(within(screen.getByRole('navigation')).getByRole('button', { name: 'Group' }));

        fireEvent.change(screen.getByRole('combobox', { name: 'Style' }), { target: { value: 'dark' } });

        await waitFor(() => expect(sent().at(-1)).toEqual({ type: 'patch', blocks: [{ key: 'g', html: '<!--cms:block g heading--><h2>g</h2><!--/cms:block g-->' }] }));
        expect(api.renderPreview).toHaveBeenCalledExactlyOnceWith('token-1', [{ ...blockOf('group', 'g', [block('a', { text: { en_US: 'Inside' } })]), style: 'dark' }]);
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);
    });

    it('should show in the right pane and save what the editor types in place in the preview', async () => {
        const api = fakeApi();
        const { sent } = await openWithBridge(api);
        fromBridge({ type: 'select', key: 'a' });

        fromBridge({ type: 'edit', key: 'a', field: 'text', value: 'Welcome back' });

        expect(screen.getByLabelText('Text')).toHaveValue('Welcome back');
        expect(treeRow('Welcome back')).toHaveAttribute('aria-pressed', 'true');
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
        expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Welcome back' }], 0);
        // Re-rendered like any edit. The bridge holds the patch back while the field is being typed in
        await waitFor(() => expect(sent().at(-1)).toMatchObject({ type: 'patch', blocks: [{ key: 'a' }] }));
    });

    it('should render what changed while a render was on its way once it is back', async () => {
        let answer: (html: Record<string, string>) => void = () => {};
        const renderPreview: Mock<BuilderApi['renderPreview']> = vi.fn(
            () =>
                new Promise((resolve) => {
                    answer = resolve;
                }),
        );
        const { sent } = await openWithBridge(fakeApi(undefined, { renderPreview }));
        fireEvent.click(treeRow('Welcome'));

        fireEvent.change(screen.getByLabelText('Text'), { target: { value: 'W' } });
        fireEvent.change(screen.getByLabelText('Text'), { target: { value: 'We' } });
        expect(renderPreview).toHaveBeenCalledTimes(1);

        await act(async () => answer({ a: 'first' }));
        await waitFor(() => expect(renderPreview).toHaveBeenCalledTimes(2));
        expect(renderPreview).toHaveBeenLastCalledWith('token-1', [block('a', { text: { en_US: 'We' } })]);
        await act(async () => answer({ a: 'second' }));

        expect(sent().filter((message) => message.type === 'patch')).toEqual([
            { type: 'patch', blocks: [{ key: 'a', html: 'first' }] },
            { type: 'patch', blocks: [{ key: 'a', html: 'second' }] },
        ]);
    });

    it('should insert a block added to the top level at its spot', async () => {
        const api = fakeApi();
        const { sent } = await openWithBridge(api);

        fireEvent.click(screen.getByRole('button', { name: 'Add block' }));
        pick('Heading');

        await waitFor(() => expect(sent().some((message) => message.type === 'insert')).toBe(true));
        const insert = sent().find((message) => message.type === 'insert');
        expect(insert).toMatchObject({ previous: ['b', 'a'], next: [] });
        expect(insert?.type === 'insert' && insert.block.html).toContain('<h2>');
    });

    it('should remove a block deleted in the tree', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fireEvent.click(within(screen.getByRole('navigation')).getAllByRole('button', { name: 'Delete block' })[0] as HTMLElement);

        expect(sent().at(-1)).toEqual({ type: 'remove', key: 'a' });
    });

    it('should reload the preview when the bridge has no place for a block', async () => {
        const api = fakeApi();
        await openWithBridge(api);
        const first = frame();

        fromBridge({ type: 'missing', key: 'a' });

        await waitFor(() => expect(frame()).toHaveAttribute('src', `${ORIGIN}/en_US/about-us?_cms_preview=token-2`));
        expect(frame()).not.toBe(first);
    });

    it("should reload the preview when the blocks can't be rendered, such as when its token expired", async () => {
        const api = fakeApi(undefined, { renderPreview: vi.fn(() => Promise.reject(new Error('The request has no valid preview token'))) });
        await openWithBridge(api);
        fireEvent.click(treeRow('Welcome'));

        fireEvent.change(screen.getByLabelText('Text'), { target: { value: 'Welcome back' } });

        await waitFor(() => expect(frame()).toHaveAttribute('src', `${ORIGIN}/en_US/about-us?_cms_preview=token-2`));
    });

    it('should patch blocks changed before the bridge was ready once it is', async () => {
        const api = fakeApi();
        render(<App config={config} api={api} autosaveDelay={10} />);
        await screen.findByRole('heading', { level: 1 });
        await waitFor(() => expect(frame()).toHaveAttribute('src'));
        fireEvent.click(treeRow('Welcome'));
        fireEvent.change(screen.getByLabelText('Text'), { target: { value: 'Welcome back' } });
        expect(api.renderPreview).not.toHaveBeenCalled();

        const { sent } = await connectBridge();

        await waitFor(() => expect(sent().at(-1)).toMatchObject({ type: 'patch', blocks: [{ key: 'a' }] }));
    });

    it('should patch the new page, not the one it replaces, with what changed while it loaded, even when the old page only gets ready then', async () => {
        let answer: (url: string) => void = () => {};
        const createPreviewUrl: Mock<BuilderApi['createPreviewUrl']> = vi.fn(async () => `${ORIGIN}/en_US/about-us?_cms_preview=token-1`);
        const api = fakeApi(undefined, { createPreviewUrl });
        render(<App config={config} api={api} autosaveDelay={10} />);
        await screen.findByRole('heading', { level: 1 });
        await waitFor(() => expect(frame()).toHaveAttribute('src', `${ORIGIN}/en_US/about-us?_cms_preview=token-1`));
        createPreviewUrl.mockImplementation(
            () =>
                new Promise((resolve) => {
                    answer = resolve;
                }),
        );
        fireEvent.change(screen.getByRole('combobox', { name: 'Shown to' }), { target: { value: '@no_group' } });
        await waitFor(() => expect(createPreviewUrl).toHaveBeenCalledTimes(2));

        // The old page was slow to load: its bridge gets ready after the new page was asked for, and the editor goes on
        const old = await connectBridge();
        fireEvent.click(treeRow('Welcome'));
        fireEvent.change(screen.getByLabelText('Text'), { target: { value: 'Welcome back' } });
        await act(async () => answer(`${ORIGIN}/en_US/about-us?_cms_preview=token-2`));
        await waitFor(() => expect(frame()).toHaveAttribute('src', `${ORIGIN}/en_US/about-us?_cms_preview=token-2`));

        // The new page was rendered from what the server held when it was asked for, so the change is patched into it
        const { sent } = await connectBridge();

        await waitFor(() => expect(sent().at(-1)).toMatchObject({ type: 'patch', blocks: [{ key: 'a' }] }));
        expect(api.renderPreview).toHaveBeenCalledExactlyOnceWith('token-2', [block('a', { text: { en_US: 'Welcome back' } })]);
        expect(old.sent()).toEqual([]);
    });

    it('should open the block picker for the spot of + in the preview, and add the block there', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fromBridge({ type: 'add', key: 'a', position: 'after', x: 120, y: 80 });
        pick('Heading');

        expect(screen.getByLabelText('Text')).toHaveValue('');
        await waitFor(() => expect(sent().find((message) => message.type === 'insert')).toMatchObject({ previous: ['a'], next: ['b'] }));
    });

    it('should open the block picker for the spot above a block', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fromBridge({ type: 'add', key: 'a', position: 'before', x: 120, y: 10 });
        pick('Heading');

        await waitFor(() => expect(sent().find((message) => message.type === 'insert')).toMatchObject({ previous: [], next: ['a', 'b'] }));
    });

    it('should add the one block type a spot allows straight away, without the picker', async () => {
        const columns = blockOf('columns', 'cols', [blockOf('column', 'left'), blockOf('column', 'right')]);
        await openWithBridge(fakeApi([columns]));

        fromBridge({ type: 'add', key: 'left', position: 'after', x: 0, y: 0 });

        expect(screen.queryByRole('dialog', { name: 'Add block' })).not.toBeInTheDocument();
        expect(within(screen.getByRole('navigation')).getAllByRole('button', { name: /^Column$/ })).toHaveLength(3);
    });

    it("should select the block's parent from its toolbar", async () => {
        const nested = blockOf('group', 'parent', [block('child', { text: { en_US: 'Inside' } })]);
        await openWithBridge(fakeApi([nested]));
        fromBridge({ type: 'select', key: 'child' });
        expect(screen.getByLabelText('Text')).toHaveValue('Inside');

        fromBridge({ type: 'select_parent', key: 'child' });

        expect(treeRow('^Group')).toHaveAttribute('aria-pressed', 'true');
    });

    it('should select nothing, so the entry, as the parent of a top-level block', async () => {
        await openWithBridge(fakeApi());
        fromBridge({ type: 'select', key: 'a' });

        fromBridge({ type: 'select_parent', key: 'a' });

        expect(screen.getByRole('button', { name: 'Entry settings', pressed: true })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Settings', selected: true })).toBeInTheDocument();
    });

    it("should take the editor to the block's settings from its toolbar", async () => {
        await openWithBridge(fakeApi());

        fromBridge({ type: 'settings', key: 'b' });

        await waitFor(() => expect(screen.getByLabelText('Text')).toHaveFocus());
        expect(screen.getByLabelText('Text')).toHaveValue('Goodbye');
    });

    it('should delete the block from its toolbar', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fromBridge({ type: 'delete', key: 'a' });

        expect(screen.queryByText('Welcome')).not.toBeInTheDocument();
        expect(sent().at(-1)).toEqual({ type: 'remove', key: 'a' });
    });

    it('should leave the builder when the bridge passes on Esc pressed in the page', async () => {
        const navigate = vi.fn();
        render(<App config={config} api={fakeApi()} autosaveDelay={10} navigate={navigate} />);
        await screen.findByRole('heading', { level: 1 });
        await waitFor(() => expect(frame()).toHaveAttribute('src'));
        await connectBridge();

        fromBridge({ type: 'leave' });

        await waitFor(() => expect(navigate).toHaveBeenCalledWith('/admin/'));
    });
});

describe('the editing tools in the preview', () => {
    function treeRows(): string[] {
        return [...screen.getByRole('navigation').querySelectorAll('li button[aria-pressed]')].map((button) => button.textContent ?? '');
    }

    it('should move the block down from its toolbar, and put it at its new spot in the preview', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fromBridge({ type: 'move', key: 'a', direction: 'down' });

        expect(treeRows()).toEqual(['HeadingGoodbye', 'HeadingWelcome']);
        await waitFor(() => expect(sent().at(-1)).toMatchObject({ type: 'insert', block: { key: 'a' }, previous: ['b'], next: [] }));
    });

    it('should duplicate the block from its toolbar, and put the copy in the preview after it', async () => {
        const { sent } = await openWithBridge(fakeApi());

        fromBridge({ type: 'duplicate', key: 'a' });

        expect(treeRows()).toEqual(['HeadingWelcome', 'HeadingWelcome', 'HeadingGoodbye']);
        await waitFor(() => expect(sent().find((message) => message.type === 'insert')).toMatchObject({ previous: ['a'], next: ['b'] }));
    });

    it("should act on the whole selection when the toolbar's block is part of it", async () => {
        await openWithBridge(fakeApi([block('a', { text: { en_US: 'First' } }), block('b', { text: { en_US: 'Second' } }), block('c', { text: { en_US: 'Third' } })]));
        fireEvent.click(treeRow('First'));
        fireEvent.click(treeRow('Third'), { metaKey: true });

        fromBridge({ type: 'duplicate', key: 'c' });
        expect(treeRows()).toEqual(['HeadingFirst', 'HeadingFirst', 'HeadingSecond', 'HeadingThird', 'HeadingThird']);

        fromBridge({ type: 'delete', key: 'b' });
        expect(treeRows()).toEqual(['HeadingFirst', 'HeadingFirst', 'HeadingThird', 'HeadingThird']);
    });

    it('should undo and redo when the bridge passes ⌘Z on, and patch the preview the same way', async () => {
        const { sent } = await openWithBridge(fakeApi());
        fromBridge({ type: 'delete', key: 'a' });

        fromBridge({ type: 'undo' });

        expect(treeRows()).toEqual(['HeadingWelcome', 'HeadingGoodbye']);
        await waitFor(() => expect(sent().at(-1)).toMatchObject({ type: 'insert', block: { key: 'a' }, previous: [], next: ['b'] }));

        fromBridge({ type: 'redo' });

        expect(treeRows()).toEqual(['HeadingGoodbye']);
        expect(sent().at(-1)).toEqual({ type: 'remove', key: 'a' });
    });
});
