import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import { applyChanges, mergeChanges, type Change } from '../src/changeSet';
import { hiddenInPreview, newCondition, withParam } from '../src/conditions';
import type { BuilderConfig } from '../src/config';
import { planPreviewUpdate } from '../src/previewPlan';
import type { Entry, EntryResponse, EntryStatus, PresenceResponse, SaveResponse, StoredBlock, ValidationError, VisibilityCondition } from '../src/types';
import { block, blockOf, entry, registry, settings, storeWith, strings } from './fixtures';

const notDanish: VisibilityCondition = { kind: 'locale', negate: true, params: { locales: ['da_DK'] } };

function withConditions(stored: StoredBlock, ...conditions: VisibilityCondition[]): StoredBlock {
    return { ...stored, conditions };
}

describe('a block hidden in the preview', () => {
    it("should be hidden by a channel or locale condition that the preview's channel and locale don't match", () => {
        const onlyWeb = { kind: 'channel', negate: false, params: { channels: ['WEB'] } };

        expect(hiddenInPreview({ conditions: [notDanish] }, 'WEB', 'da_DK')).toBe(true);
        expect(hiddenInPreview({ conditions: [notDanish] }, 'WEB', 'en_US')).toBe(false);
        expect(hiddenInPreview({ conditions: [onlyWeb] }, 'B2B', 'en_US')).toBe(true);
        expect(hiddenInPreview({ conditions: [onlyWeb] }, 'WEB', 'en_US')).toBe(false);
        expect(hiddenInPreview({ conditions: [{ kind: 'locale', negate: false, params: { locales: ['en_US', 'da_DK'] } }] }, 'WEB', 'da_DK')).toBe(false);
    });

    it('should be hidden by a condition with no values, which matches nobody', () => {
        expect(hiddenInPreview({ conditions: [{ kind: 'locale', negate: false, params: {} }] }, 'WEB', 'en_US')).toBe(true);
        expect(hiddenInPreview({ conditions: [{ kind: 'locale', negate: true, params: {} }] }, 'WEB', 'en_US')).toBe(false);
    });

    it('should not count conditions of other kinds, nor a channel condition while the preview has no channel', () => {
        expect(hiddenInPreview({ conditions: [{ kind: 'customer_group', negate: false, params: { groups: [] } }] }, 'WEB', 'en_US')).toBe(false);
        expect(hiddenInPreview({ conditions: [{ kind: 'channel', negate: false, params: { channels: [] } }] }, null, 'en_US')).toBe(false);
        expect(hiddenInPreview({ conditions: [] }, 'WEB', 'en_US')).toBe(false);
    });
});

describe('editing a condition', () => {
    it('should start a new condition as "is one of" with no values', () => {
        expect(newCondition('locale')).toEqual({ kind: 'locale', negate: false, params: {} });
    });

    it('should set a parameter, and take it away when it is empty', () => {
        const condition = withParam(newCondition('locale'), 'locales', ['da_DK']);

        expect(condition).toEqual({ kind: 'locale', negate: false, params: { locales: ['da_DK'] } });
        expect(withParam(condition, 'locales', [])).toEqual({ kind: 'locale', negate: false, params: {} });
        expect(withParam({ kind: 'date_window', negate: true, params: { from: '2026-11-27T00:00:00Z' } }, 'until', '2026-12-01T00:00:00Z')).toEqual({
            kind: 'date_window',
            negate: true,
            params: { from: '2026-11-27T00:00:00Z', until: '2026-12-01T00:00:00Z' },
        });
        expect(withParam({ kind: 'date_window', negate: false, params: { from: '2026-11-27T00:00:00Z' } }, 'from', null).params).toEqual({});
    });
});

describe("saving a block's conditions", () => {
    it('should save all of them as one set_conditions change, the last one set', () => {
        const store = storeWith([block('a'), block('b')]);

        store.getState().setConditions('a', [newCondition('locale')]);
        store.getState().setConditions('b', [notDanish]);
        store.getState().setConditions('a', [notDanish, newCondition('channel')]);

        expect(store.getState().entry.tree.blocks[0]?.conditions).toEqual([notDanish, newCondition('channel')]);
        expect(store.getState().unsaved).toEqual<Change[]>([
            { op: 'set_conditions', key: 'b', conditions: [notDanish] },
            { op: 'set_conditions', key: 'a', conditions: [notDanish, newCondition('channel')] },
        ]);
    });

    it("should undo the changes to one block's conditions one after another in one step", () => {
        const store = storeWith([block('a'), block('b')]);

        store.getState().setConditions('a', [newCondition('locale')]);
        store.getState().setConditions('a', [notDanish]);
        store.getState().setConditions('b', [notDanish]);

        store.getState().undo();
        expect(store.getState().entry.tree.blocks[1]?.conditions).toEqual([]);
        expect(store.getState().entry.tree.blocks[0]?.conditions).toEqual([notDanish]);

        store.getState().undo();
        expect(store.getState().entry.tree.blocks[0]?.conditions).toEqual([]);
        expect(store.getState().unsaved).toEqual<Change[]>([
            { op: 'set_conditions', key: 'b', conditions: [] },
            { op: 'set_conditions', key: 'a', conditions: [] },
        ]);
    });

    it('should not record setting the conditions a block already has', () => {
        const store = storeWith([withConditions(block('a'), notDanish)]);

        store.getState().setConditions('a', [{ ...notDanish, params: { locales: ['da_DK'] } }]);

        expect(store.getState().unsaved).toEqual([]);
    });

    it('should send the conditions of a new block with it, and those of a block put back by undo', () => {
        const store = storeWith([withConditions(block('a'), notDanish)]);

        const key = store.getState().addBlock('heading') ?? '';
        store.getState().setConditions(key, [notDanish]);
        expect(store.getState().unsaved.map((change) => change.op)).toEqual(['add_block', 'set_conditions']);

        store.getState().removeBlock('a');
        store.getState().undo();
        expect(store.getState().unsaved.at(-1)).toEqual({ op: 'add_block', parent: null, index: 0, block: withConditions(block('a'), notDanish) });
    });

    it('should drop the conditions set on a saved block that gets deleted', () => {
        const store = storeWith([block('a')]);

        store.getState().setConditions('a', [notDanish]);
        store.getState().removeBlock('a');

        expect(store.getState().unsaved).toEqual<Change[]>([{ op: 'remove_block', key: 'a' }]);
    });

    it("should apply a colleague's conditions, and leave out those for a block that's gone", () => {
        const base: Entry = entry([block('a')]);

        const { entry: applied, applied: changes, missing } = applyChanges(base, [
            { op: 'set_conditions', key: 'a', conditions: [notDanish] },
            { op: 'set_conditions', key: 'gone', conditions: [] },
        ]);

        expect(applied.tree.blocks[0]?.conditions).toEqual([notDanish]);
        expect(changes).toEqual([{ op: 'set_conditions', key: 'a', conditions: [notDanish] }]);
        expect(missing).toEqual(['gone']);
        expect(mergeChanges([{ op: 'set_conditions', key: 'a', conditions: [] }], changes)).toEqual(changes);
    });
});

describe('the preview after a change to conditions', () => {
    it('should re-render the parent of a block whose conditions changed, which may show another child', () => {
        const store = storeWith([blockOf('group', 'g', [block('a'), block('b')]), block('c')]);
        const before = store.getState().entry;

        store.getState().setConditions('a', [notDanish]);
        expect(planPreviewUpdate(before, store.getState().entry)).toEqual({ reload: false, patch: ['g'], insert: [], remove: [] });

        const next = store.getState().entry;
        store.getState().setConditions('c', [notDanish]);
        expect(planPreviewUpdate(next, store.getState().entry)).toEqual({ reload: false, patch: ['c'], insert: [], remove: [] });
    });
});

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
    channels: [{ code: 'WEB', name: 'Web store', locales: ['en_US', 'da_DK'] }],
    locales: [
        { code: 'en_US', name: 'English (United States)', fallbackChain: ['en_US'] },
        { code: 'da_DK', name: 'Danish (Denmark)', fallbackChain: ['da_DK', 'en_US'] },
    ],
    customerGroups: [],
};

const conditionStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.locale': 'Language',
    'setono_sylius_cms.builder.visibility': 'Visibility',
    'setono_sylius_cms.builder.visibility_help': 'Shows to everyone.',
    'setono_sylius_cms.builder.add_condition': 'Add condition',
    'setono_sylius_cms.builder.remove_condition': 'Remove condition',
    'setono_sylius_cms.builder.condition': 'Condition %number%',
    'setono_sylius_cms.builder.condition_kind': 'Kind',
    'setono_sylius_cms.builder.condition_match': 'Matches when it',
    'setono_sylius_cms.builder.is_one_of': 'is one of',
    'setono_sylius_cms.builder.is_not_one_of': 'is not one of',
    'setono_sylius_cms.builder.is_inside': 'is inside',
    'setono_sylius_cms.builder.is_outside': 'is outside',
    'setono_sylius_cms.builder.unknown_condition_kind': 'Unknown condition: %kind%',
    'setono_sylius_cms.builder.unknown_condition_kind_help': "The code no longer has this kind of condition, so the block doesn't show.",
    'setono_sylius_cms.builder.hidden_in_preview': 'Hidden in this channel and language',
};

async function openBuilder(...blocks: StoredBlock[]) {
    const edited = entry(blocks);
    const withDanish: Entry = { ...edited, translations: { ...edited.translations, da_DK: { shown: true, fields: { title: 'Om os', slug: 'om-os' }, translated: {} } } };
    const api = {
        fetchRegistry: vi.fn(async () => ({ ...registry, strings: conditionStrings })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: withDanish, settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: withDanish, settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    } satisfies BuilderApi;
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });

    return api;
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

describe('the visibility conditions in the right pane', () => {
    it('should add a condition, pick its kind, "is not one of" and its values, and autosave them', async () => {
        const api = await openBuilder(block('a', { text: { en_US: 'Hello' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Hello' }));

        expect(within(selectionPane()).getByText('Shows to everyone.')).toBeInTheDocument();
        fireEvent.click(within(selectionPane()).getByRole('button', { name: 'Add condition' }));

        const condition = within(selectionPane()).getByRole('group', { name: 'Condition 1' });
        const kind = within(condition).getByRole('combobox', { name: 'Kind' });
        expect(within(kind).getAllByRole('option').map((option) => option.textContent)).toEqual(['Channel', 'Language', 'Date window', 'Customer group']);
        expect(kind).toHaveValue('channel');

        fireEvent.change(kind, { target: { value: 'locale' } });
        fireEvent.change(within(condition).getByRole('combobox', { name: 'Matches when it' }), { target: { value: 'not' } });
        const languages = within(condition).getByRole('listbox', { name: 'Languages' });
        within(languages).getByRole<HTMLOptionElement>('option', { name: 'Danish (Denmark)' }).selected = true;
        fireEvent.change(languages);

        expect(within(condition).queryByText('All languages')).not.toBeInTheDocument();
        await waitFor(() => expect(api.saveChanges).toHaveBeenLastCalledWith([{ op: 'set_conditions', key: 'a', conditions: [notDanish] }], expect.any(Number)));
    });

    it('should say "is inside" and "is outside" for a date window, and take dates and times in the editor\'s time zone', async () => {
        await openBuilder(withConditions(block('a', { text: { en_US: 'Hello' } }), { kind: 'date_window', negate: true, params: { from: '2026-11-27T00:00:00Z' } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Hello' }));

        const condition = within(selectionPane()).getByRole('group', { name: 'Condition 1' });
        const match = within(condition).getByRole('combobox', { name: 'Matches when it' });
        expect(within(match).getAllByRole('option').map((option) => option.textContent)).toEqual(['is inside', 'is outside']);
        expect(match).toHaveValue('not');
        expect(within(condition).getByLabelText('From')).toHaveValue(toLocal('2026-11-27T00:00:00Z'));
        expect(within(condition).getByLabelText('Until')).toHaveValue('');
    });

    it('should remove a condition', async () => {
        const api = await openBuilder(withConditions(block('a', { text: { en_US: 'Hello' } }), notDanish, { kind: 'channel', negate: false, params: { channels: ['WEB'] } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Hello' }));

        fireEvent.click(within(within(selectionPane()).getByRole('group', { name: 'Condition 1' })).getByRole('button', { name: 'Remove condition' }));

        expect(within(selectionPane()).getByRole('combobox', { name: 'Kind' })).toHaveValue('channel');
        await waitFor(() =>
            expect(api.saveChanges).toHaveBeenLastCalledWith([{ op: 'set_conditions', key: 'a', conditions: [{ kind: 'channel', negate: false, params: { channels: ['WEB'] } }] }], expect.any(Number)),
        );
    });

    it('should show a condition of a kind the code no longer has, which can only be removed or replaced', async () => {
        await openBuilder(withConditions(block('a', { text: { en_US: 'Hello' } }), { kind: 'weekday', negate: false, params: { days: ['monday'] } }));
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Hello' }));

        const condition = within(selectionPane()).getByRole('group', { name: 'Condition 1' });
        expect(within(condition).getByRole('combobox', { name: 'Kind' })).toHaveValue('weekday');
        expect(within(condition).getByRole('option', { name: 'Unknown condition: weekday' })).toBeInTheDocument();
        expect(within(condition).getByText("The code no longer has this kind of condition, so the block doesn't show.")).toBeInTheDocument();
        expect(within(condition).queryByRole('combobox', { name: 'Matches when it' })).not.toBeInTheDocument();
    });
});

describe('the block tree', () => {
    it("should strike through the blocks the preview's language hides, with everything inside them", async () => {
        await openBuilder(withConditions(blockOf('group', 'g', [block('a', { text: { en_US: 'Inside' } })]), notDanish), block('b', { text: { en_US: 'Outside' } }));
        const row = (name: string): HTMLElement => within(tree()).getByRole('button', { name }).parentElement as HTMLElement;
        // The rows' hints show the edited locale's text, so they're found in English
        const [group, inside, outside] = [row('Group'), row('Heading Inside'), row('Heading Outside')];

        expect(within(tree()).queryByRole('img', { name: 'Hidden in this channel and language' })).not.toBeInTheDocument();

        fireEvent.change(screen.getByRole('combobox', { name: 'Language' }), { target: { value: 'da_DK' } });

        expect(group).toHaveAttribute('data-hidden');
        expect(within(group).getByRole('img', { name: 'Hidden in this channel and language' })).toBeInTheDocument();
        expect(inside).toHaveAttribute('data-hidden');
        expect(outside).not.toHaveAttribute('data-hidden');
        expect(within(tree()).getAllByRole('img', { name: 'Hidden in this channel and language' })).toHaveLength(2);
    });
});

/** What a datetime-local input shows for a UTC time, in the time zone the tests run in. */
function toLocal(utc: string): string {
    const date = new Date(utc);
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
