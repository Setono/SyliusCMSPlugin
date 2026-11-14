import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import type { Change } from '../src/changeSet';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus } from '../src/types';
import { block, entry, registry, strings } from './fixtures';

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    endpoints: { registry: '/registry', entry: '/entry', publish: '/publish' },
    strings: { 'setono_sylius_cms.builder.loading': 'Loading…' },
};

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), status: 'never_published' })),
        saveChanges: vi.fn(async (): Promise<EntryStatus> => 'never_published'),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi = fakeApi()) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function addBlock(label: string) {
    fireEvent.keyDown(screen.getByRole('button', { name: 'Add block' }), { key: 'Enter' });
    fireEvent.click(screen.getByRole('menuitem', { name: label }));
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
        expect(screen.getByText('Select a block to edit its fields')).toBeInTheDocument();
    });

    it('should add a heading, autosave its text and publish it', async () => {
        const saveChanges = vi.fn(async (_changes: readonly Change[]): Promise<EntryStatus> => 'never_published');
        const publish = vi.fn(async (): Promise<EntryStatus> => 'published');
        await openBuilder(fakeApi({ saveChanges, publish }));

        addBlock('Heading');

        expect(screen.getByRole('button', { name: 'Heading', pressed: true })).toBeInTheDocument();
        fireEvent.change(screen.getByRole('textbox', { name: 'Text' }), { target: { value: 'Hello' } });
        expect(screen.getByRole('status')).toHaveTextContent('Saving…');
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));

        expect(saveChanges).toHaveBeenCalledTimes(1);
        expect(saveChanges.mock.calls[0]?.[0]).toEqual([
            { op: 'add_block', parent: null, index: 1, block: expect.objectContaining({ type: 'heading', version: 1, fields: {} }) },
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

    it('should say so when the entry cannot be loaded', async () => {
        render(<App config={config} api={fakeApi({ fetchEntry: () => Promise.reject(new Error('Not found')) })} />);

        expect(await screen.findByRole('alert')).toHaveTextContent('setono_sylius_cms.builder.load_failed');
    });

    it('should take its UI text from the registry strings', async () => {
        await openBuilder(fakeApi({ fetchRegistry: async () => ({ ...registry, strings: { ...strings, 'setono_sylius_cms.builder.add_block': 'Tilføj blok' } }) }));

        expect(screen.getByRole('button', { name: 'Tilføj blok' })).toBeInTheDocument();
    });
});
