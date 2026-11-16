import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ApiError, type BuilderApi } from '../src/api';
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
        schedule: '/schedule',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

const required: ValidationError = { block: 'empty', field: 'text', locale: 'en_US', message: 'This field is required.' };
const tooFew: ValidationError = { block: 'cols', field: null, locale: null, message: 'Needs at least 2 Column blocks inside.' };

/** A filled heading, an empty one, and Columns with one Column. */
const blocks = (): StoredBlock[] => [block('filled', { text: { en_US: 'Welcome' } }), block('empty'), blockOf('columns', 'cols', [blockOf('column', 'c1')])];

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks()), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => [required, tooFew]),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks()), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi = fakeApi()) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

/** The row of the block whose row button has this name. */
function row(name: string, nth = 0): HTMLElement {
    const element = within(tree()).getAllByRole('button', { name })[nth]?.parentElement;
    if (!(element instanceof HTMLElement)) {
        throw new Error(`The tree has no block "${name}"`);
    }

    return element;
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

describe('validation in the builder', () => {
    it('should flag the invalid blocks in the tree once it opens', async () => {
        const api = fakeApi();
        await openBuilder(api);

        await waitFor(() => expect(within(row('Heading')).getByRole('img', { name: 'Has errors' })).toBeInTheDocument());
        expect(within(row('Columns')).getByRole('img', { name: 'Has errors' })).toBeInTheDocument();
        expect(within(row('Heading Welcome')).queryByRole('img', { name: 'Has errors' })).not.toBeInTheDocument();
        expect(within(row('Column')).queryByRole('img', { name: 'Has errors' })).not.toBeInTheDocument();
        expect(api.validate).toHaveBeenCalledTimes(1);
    });

    it("should show a selected block's errors next to its fields, and the errors about the block as a whole", async () => {
        await openBuilder();
        await within(row('Heading')).findByRole('img', { name: 'Has errors' });

        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading' }));

        expect(within(selectionPane()).getByRole('textbox', { name: 'Text' })).toHaveAccessibleDescription('This field is required.');

        fireEvent.click(within(tree()).getByRole('button', { name: 'Columns' }));

        expect(within(selectionPane()).getByText('Needs at least 2 Column blocks inside.')).toBeInTheDocument();
    });

    it('should validate again after each save, so a fixed block is no longer flagged', async () => {
        const validate = vi.fn<() => Promise<ValidationError[]>>().mockResolvedValueOnce([required, tooFew]).mockResolvedValue([tooFew]);
        const api = fakeApi({ validate });
        await openBuilder(api);
        await within(row('Heading')).findByRole('img', { name: 'Has errors' });

        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading' }));
        fireEvent.change(within(selectionPane()).getByRole('textbox', { name: 'Text' }), { target: { value: 'Hello' } });

        await waitFor(() => expect(validate).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(within(row('Heading Hello')).queryByRole('img', { name: 'Has errors' })).not.toBeInTheDocument());
        expect(api.saveChanges).toHaveBeenCalledTimes(1);
        expect(within(row('Columns')).getByRole('img', { name: 'Has errors' })).toBeInTheDocument();
    });

    it('should select the first block with an error after a refused publish, and say why it was refused', async () => {
        const api = fakeApi({
            validate: vi.fn(async (): Promise<ValidationError[]> => []),
            publish: vi.fn(() => Promise.reject(new ApiError('2 errors need fixing', 422, [tooFew, required]))),
        });
        await openBuilder(api);

        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await screen.findByRole('alert')).toHaveTextContent("Couldn't publish: 2 errors need fixing");
        expect(within(tree()).getByRole('button', { name: 'Heading' })).toHaveAttribute('aria-pressed', 'true');
        expect(within(selectionPane()).getByRole('textbox', { name: 'Text' })).toHaveAccessibleDescription('This field is required.');
        expect(within(row('Columns')).getByRole('img', { name: 'Has errors' })).toBeInTheDocument();
    });

    it("should show the entry's own errors in its settings after a refused publish", async () => {
        const entryError: ValidationError = { block: null, field: null, locale: null, message: 'Needs at least one Heading block inside.' };
        await openBuilder(
            fakeApi({
                validate: vi.fn(async (): Promise<ValidationError[]> => []),
                publish: vi.fn(() => Promise.reject(new ApiError('One error needs fixing', 422, [entryError]))),
            }),
        );
        fireEvent.click(within(tree()).getByRole('button', { name: 'Columns' }));

        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await within(selectionPane()).findByText('Needs at least one Heading block inside.')).toBeInTheDocument();
        expect(within(tree()).getByRole('button', { name: 'Entry settings' })).toHaveAttribute('aria-pressed', 'true');
        expect(within(row('Entry settings')).getByRole('img', { name: 'Has errors' })).toBeInTheDocument();
    });

    it('should clear the errors after a publish goes through', async () => {
        await openBuilder(fakeApi());
        await within(row('Heading')).findByRole('img', { name: 'Has errors' });

        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        await waitFor(() => expect(within(tree()).queryByRole('img', { name: 'Has errors' })).not.toBeInTheDocument());
    });

    it('should show a block of a type the code no longer has as unknown', async () => {
        await openBuilder(
            fakeApi({
                fetchEntry: async () => ({ entry: entry([blockOf('banner', 'b', [], { title: 'Winter sale' })]), settings: settings(), status: 'never_published', lockToken: 0, editors: [] }),
                validate: vi.fn(async (): Promise<ValidationError[]> => [{ block: 'b', field: null, locale: null, message: 'Its block type "banner" no longer exists, so it isn\'t shown.' }]),
            }),
        );

        fireEvent.click(within(tree()).getByRole('button', { name: 'Unknown block type: banner' }));

        expect(within(selectionPane()).getByRole('heading', { name: 'Unknown block type: banner' })).toBeInTheDocument();
        expect(within(selectionPane()).getByText("The code no longer has this block type. The block is kept as it is, but the shop doesn't show it.")).toBeInTheDocument();
        expect(await within(selectionPane()).findByText('Its block type "banner" no longer exists, so it isn\'t shown.')).toBeInTheDocument();
    });
});
