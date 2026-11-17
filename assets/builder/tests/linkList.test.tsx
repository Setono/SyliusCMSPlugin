import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { Change } from '../src/changeSet';
import type { BuilderConfig } from '../src/config';
import type { SearchApi, SearchResult } from '../src/search/api';
import type { BlockTypeDefinition, EntryResponse, EntryStatus, FieldSchema, PresenceResponse, Registry, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { blockOf, entry, page, registry as baseRegistry, settings } from './fixtures';

const linkSchema: FieldSchema = {
    type: 'object',
    'x-cms-type': 'link',
    'x-cms-label': 'Link',
    'x-cms-help': null,
    'x-cms-required': true,
    'x-cms-translatable': false,
    'x-cms-options': { targets: ['product', 'url'], translatable: null },
};

const linkList: BlockTypeDefinition = {
    code: 'link_list',
    label: 'Link list',
    version: 1,
    thumbnail: null,
    disabled: false,
    parents: null,
    children: [{ type: 'link', min: null, max: null }],
    styles: [],
    fields: [{ name: 'heading', schema: { type: 'string', 'x-cms-type': 'text', 'x-cms-label': 'Heading', 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': true } }],
};

const link: BlockTypeDefinition = {
    code: 'link',
    label: 'Link',
    version: 1,
    thumbnail: null,
    disabled: false,
    parents: ['link_list'],
    children: [],
    styles: [],
    fields: [{ name: 'link', schema: linkSchema }],
};

const registry: Registry = {
    ...baseRegistry,
    blockTypes: [...baseRegistry.blockTypes, linkList, link],
    contentTypes: [{ ...page, blocks: [...(page.blocks ?? []), { type: 'link_list', min: null, max: null }] }],
    linkTargets: [
        { code: 'product', label: 'Product', translatableRef: false },
        { code: 'url', label: 'URL', translatableRef: true },
    ],
    strings: {
        ...baseRegistry.strings,
        'setono_sylius_cms.builder.link.row_label': 'Link label',
    },
};

const products: SearchResult[] = [{ ref: 'T_SHIRT_RED', name: 'Red T-shirt', thumbnail: null }];

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

async function openBuilder(...blocks: StoredBlock[]) {
    const api = {
        fetchRegistry: vi.fn(async () => registry),
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
    } satisfies BuilderApi;
    const searchApi: SearchApi = {
        search: vi.fn(async () => products),
        find: vi.fn(async (kind: string, refs: readonly string[]) => (kind === 'product' ? products.filter(({ ref }) => refs.includes(ref)) : [])),
    };
    render(<App config={config} api={api} searchApi={searchApi} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });

    return api;
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

describe('a Link list in the tree', () => {
    it("should give each Link's row a box for its label in the edited locale, which shows the name of what it links to when it's empty", async () => {
        await openBuilder(
            blockOf('link_list', 'l', [
                blockOf('link', 'k1', [], { link: { target: 'url', ref: { en_US: '/en_US/faq' }, anchor: null, label: { en_US: 'Returns' } } }),
                blockOf('link', 'k2', [], { link: { target: 'product', ref: 'T_SHIRT_RED', anchor: null, label: {} } }),
            ]),
        );

        const labels = within(tree()).getAllByRole('textbox', { name: 'Link label' });
        expect(labels).toHaveLength(2);
        expect(labels[0]).toHaveValue('Returns');
        expect(labels[1]).toHaveValue('');
        await waitFor(() => expect(labels[1]).toHaveAttribute('placeholder', 'Red T-shirt'));
    });

    it("should save a label typed in a Link's row into its link, keeping the rest", async () => {
        const api = await openBuilder(blockOf('link_list', 'l', [blockOf('link', 'k1', [], { link: { target: 'product', ref: 'T_SHIRT_RED', anchor: 'sizes', label: {} } })]));

        fireEvent.change(within(tree()).getByRole('textbox', { name: 'Link label' }), { target: { value: 'Shop shirts' } });

        await waitFor(() =>
            expect(api.saveChanges).toHaveBeenCalledWith(
                [{ op: 'set_value', key: 'k1', field: 'link', locale: null, value: { target: 'product', ref: 'T_SHIRT_RED', anchor: 'sizes', label: { en_US: 'Shop shirts' } } }],
                0,
            ),
        );
        expect(within(tree()).getByRole('textbox', { name: 'Link label' })).toHaveValue('Shop shirts');
    });

    it('should add a Link straight away to a Link list that is added', async () => {
        await openBuilder();

        fireEvent.click(screen.getByRole('button', { name: 'Add block' }));
        fireEvent.click(within(screen.getByRole('dialog', { name: 'Add block' })).getByRole('button', { name: 'Link list' }));

        expect(within(tree()).getByRole('button', { name: 'Link' })).toBeInTheDocument();
        expect(within(tree()).getByRole('textbox', { name: 'Link label' })).toHaveValue('');
    });

    it('should give no row but a Link a label box', async () => {
        await openBuilder(blockOf('link_list', 'l'));

        expect(within(tree()).queryByRole('textbox')).not.toBeInTheDocument();
    });
});
