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

const strings: Record<string, string> = {
    ...registry.strings,
    'setono_sylius_cms.builder.also_editing': 'Also editing',
    'setono_sylius_cms.builder.clash': 'A colleague changed this too',
    'setono_sylius_cms.builder.clash_count': '%index% of %count%',
    'setono_sylius_cms.builder.clash_value': 'You and a colleague both changed %field% in %block%.',
    'setono_sylius_cms.builder.clash_deleted_block': 'A colleague deleted %block%, which you changed.',
    'setono_sylius_cms.builder.clash_changed_block': 'You deleted %block%, which a colleague changed.',
    'setono_sylius_cms.builder.clash_yours': 'Yours',
    'setono_sylius_cms.builder.clash_theirs': 'Theirs',
    'setono_sylius_cms.builder.clash_empty': 'Nothing',
    'setono_sylius_cms.builder.keep_mine': 'Keep mine',
    'setono_sylius_cms.builder.take_theirs': 'Take theirs',
};

function fakeApi(blocks: StoredBlock[], overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, strings })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), lockToken: 1, settings: settings(), status: 'never_published', editors: [] })),
        saveChanges: vi.fn(async (_changes: readonly Change[], _lockToken: number): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 2 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(blocks), lockToken: 1, settings: settings(), status: 'published', editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 1 })),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

describe('editing alongside colleagues', () => {
    it('should show who else has the entry open in the top bar, as the server says', async () => {
        const presence = vi
            .fn<() => Promise<PresenceResponse>>()
            .mockResolvedValueOnce({ editors: [{ name: 'Jane Roe' }], lockToken: 1 })
            .mockResolvedValue({ editors: [], lockToken: 1 });
        const api = fakeApi([block('a')], {
            fetchEntry: async () => ({ entry: entry([block('a')]), lockToken: 1, settings: settings(), status: 'never_published', editors: [{ name: 'Max Mustermann' }] }),
            presence,
        });

        await openBuilder(api);

        const list = await screen.findByRole('list', { name: 'Also editing' });
        await waitFor(() => expect(list).toHaveTextContent('Jane Roe'));
        expect(list).not.toHaveTextContent('Max Mustermann');
        expect(within(list).getByTitle('Jane Roe')).toHaveTextContent('JR');
    });

    it('should show nobody when nobody else has the entry open', async () => {
        await openBuilder(fakeApi([block('a')]));

        expect(screen.queryByRole('list', { name: 'Also editing' })).toBeNull();
    });

    it('should save with the lock token and pull in the colleagues\' changes the answer carries', async () => {
        const theirs = entry([block('a', { text: { en_US: 'Hello there' } }), block('b', { text: { en_US: 'Theirs' } })]);
        const saveChanges = vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 3, entry: theirs, clashes: [] }));
        await openBuilder(fakeApi([block('a', { text: { en_US: 'Hello' } })], { saveChanges }));

        fireEvent.click(screen.getByRole('button', { name: 'Heading Hello' }));
        fireEvent.change(screen.getByRole('textbox', { name: 'Text' }), { target: { value: 'Hello there' } });

        expect(await screen.findByRole('button', { name: 'Heading Theirs' })).toBeInTheDocument();
        expect(saveChanges).toHaveBeenCalledWith([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Hello there' }], 1);
        expect(screen.getByRole('status')).toHaveTextContent('Saved');
    });

    it('should show the clashes a save comes back with one at a time, and keep mine or take theirs', async () => {
        const theirs = entry([block('a', { text: { en_US: 'Theirs' } }), blockOf('group', 'g', [block('b')])]);
        const saveChanges = vi
            .fn(async (_changes: readonly Change[], _lockToken: number): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 3 }))
            .mockResolvedValueOnce({
                status: 'never_published',
                lockToken: 2,
                entry: theirs,
                clashes: [
                    { kind: 'value', key: 'a', field: 'text', locale: 'en_US', mine: 'Mine', theirs: 'Theirs' },
                    { kind: 'changed_block', key: 'g' },
                ],
            });
        await openBuilder(fakeApi([block('a', { text: { en_US: 'Hello' } }), blockOf('group', 'g', [block('b')])], { saveChanges }));

        fireEvent.click(screen.getByRole('button', { name: 'Heading Hello' }));
        fireEvent.change(screen.getByRole('textbox', { name: 'Text' }), { target: { value: 'Mine' } });

        const first = await screen.findByRole('dialog', { name: 'A colleague changed this too' });
        expect(first).toHaveTextContent('You and a colleague both changed Text in Heading.');
        expect(first).toHaveTextContent('1 of 2');
        expect(within(first).getByRole('definition', { name: 'Yours' })).toHaveTextContent('Mine');
        expect(within(first).getByRole('definition', { name: 'Theirs' })).toHaveTextContent('Theirs');

        fireEvent.click(within(first).getByRole('button', { name: 'Keep mine' }));

        // The dialog hides the rest of the builder from assistive technology while it's open
        expect(screen.getByRole('textbox', { name: 'Text', hidden: true })).toHaveValue('Mine');
        const second = screen.getByRole('dialog', { name: 'A colleague changed this too' });
        expect(second).toHaveTextContent('You deleted Group, which a colleague changed.');
        expect(second).toHaveTextContent('2 of 2');

        fireEvent.click(within(second).getByRole('button', { name: 'Take theirs' }));

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(screen.getByRole('textbox', { name: 'Text' })).toHaveValue('Mine');
        expect(screen.getByRole('button', { name: 'Group' })).toBeInTheDocument();
        await waitFor(() => expect(saveChanges).toHaveBeenCalledTimes(2));
        expect(saveChanges).toHaveBeenLastCalledWith([{ op: 'set_value', key: 'a', field: 'text', locale: 'en_US', value: 'Mine' }], 2);
    });

    it('should put a block a colleague deleted back when the editor keeps their changes to it', async () => {
        const saveChanges = vi
            .fn(async (_changes: readonly Change[], _lockToken: number): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 3 }))
            .mockResolvedValueOnce({ status: 'never_published', lockToken: 2, entry: entry([block('a')]), clashes: [{ kind: 'deleted_block', key: 'b' }] });
        await openBuilder(fakeApi([block('a'), block('b', { text: { en_US: 'Hello' } })], { saveChanges }));

        fireEvent.click(screen.getByRole('button', { name: 'Heading Hello' }));
        fireEvent.change(screen.getByRole('textbox', { name: 'Text' }), { target: { value: 'Mine' } });

        const dialog = await screen.findByRole('dialog', { name: 'A colleague changed this too' });
        expect(dialog).toHaveTextContent('A colleague deleted Heading, which you changed.');
        expect(screen.queryByRole('button', { name: 'Heading Mine' })).toBeNull();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Keep mine' }));

        expect(screen.getByRole('button', { name: 'Heading Mine' })).toBeInTheDocument();
        await waitFor(() => expect(saveChanges).toHaveBeenCalledTimes(2));
        expect(saveChanges).toHaveBeenLastCalledWith([{ op: 'add_block', parent: null, index: 1, after: 'a', block: block('b', { text: { en_US: 'Mine' } }) }], 2);
    });

    it('should pull in what colleagues saved when presence says the working copy moved on', async () => {
        const theirs = entry([block('a', { text: { en_US: 'Theirs' } })]);
        const saveChanges = vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 2, entry: theirs, clashes: [] }));
        await openBuilder(fakeApi([block('a', { text: { en_US: 'Hello' } })], { saveChanges, presence: async () => ({ editors: [{ name: 'Jane Roe' }], lockToken: 2 }) }));

        expect(await screen.findByRole('button', { name: 'Heading Theirs' })).toBeInTheDocument();
        expect(saveChanges).toHaveBeenCalledWith([], 1);
    });
});
