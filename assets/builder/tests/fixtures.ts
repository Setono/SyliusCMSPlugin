import { createBuilderStore, type BuilderStore } from '../src/store';
import type { AllowedBlockType, BlockTypeDefinition, ContentTypeDefinition, Entry, FieldValues, Registry, StoredBlock } from '../src/types';

/** What '*' resolves to everywhere but inside Columns: every block type except Column. */
const anyBlock: AllowedBlockType[] = ['group', 'columns', 'spacer', 'heading'].map((type) => ({ type, min: null, max: null }));

export const heading: BlockTypeDefinition = {
    code: 'heading',
    label: 'Heading',
    version: 1,
    thumbnail: '/thumbnail/heading.svg',
    disabled: false,
    parents: null,
    children: [],
    fields: [
        {
            name: 'text',
            schema: {
                type: 'string',
                'x-cms-type': 'text',
                'x-cms-label': 'Text',
                'x-cms-help': null,
                'x-cms-required': true,
                'x-cms-translatable': true,
                'x-cms-options': { maxLength: null },
            },
        },
        {
            name: 'level',
            schema: {
                type: 'string',
                enum: ['h1', 'h2', 'h3', 'h4'],
                default: 'h2',
                'x-cms-type': 'choice',
                'x-cms-label': 'Level',
                'x-cms-help': null,
                'x-cms-required': false,
                'x-cms-translatable': false,
                'x-cms-options': { choices: { h1: 'Heading 1', h2: 'Heading 2', h3: 'Heading 3', h4: 'Heading 4' }, multiple: false },
            },
        },
    ],
};

export const group: BlockTypeDefinition = {
    code: 'group',
    label: 'Group',
    version: 1,
    thumbnail: '/thumbnail/group.svg',
    disabled: false,
    parents: null,
    children: anyBlock,
    fields: [
        {
            name: 'fullWidth',
            schema: {
                type: 'boolean',
                default: false,
                'x-cms-type': 'toggle',
                'x-cms-label': 'Full width',
                'x-cms-help': null,
                'x-cms-required': false,
                'x-cms-translatable': false,
            },
        },
    ],
};

export const columns: BlockTypeDefinition = {
    code: 'columns',
    label: 'Columns',
    version: 1,
    thumbnail: '/thumbnail/columns.svg',
    disabled: false,
    parents: null,
    children: [{ type: 'column', min: 2, max: 4 }],
    fields: [],
};

export const column: BlockTypeDefinition = {
    code: 'column',
    label: 'Column',
    version: 1,
    thumbnail: '/thumbnail/column.svg',
    disabled: false,
    parents: ['columns'],
    children: anyBlock,
    fields: [],
};

export const spacer: BlockTypeDefinition = {
    code: 'spacer',
    label: 'Spacer',
    version: 1,
    thumbnail: '/thumbnail/spacer.svg',
    disabled: false,
    parents: null,
    children: [],
    fields: [],
};

export const blockTypes: BlockTypeDefinition[] = [group, columns, column, spacer, heading];

export const page: ContentTypeDefinition = { code: 'page', label: 'Page', blocks: anyBlock };

export const strings: Record<string, string> = {
    'setono_sylius_cms.builder.loading': 'Loading…',
    'setono_sylius_cms.builder.back': 'Back',
    'setono_sylius_cms.builder.saving': 'Saving…',
    'setono_sylius_cms.builder.saved': 'Saved',
    'setono_sylius_cms.builder.save_failed': "Couldn't save",
    'setono_sylius_cms.builder.publish': 'Publish',
    'setono_sylius_cms.builder.publishing': 'Publishing…',
    'setono_sylius_cms.builder.published': 'Published',
    'setono_sylius_cms.builder.never_published': 'Never published',
    'setono_sylius_cms.builder.publish_failed': "Couldn't publish",
    'setono_sylius_cms.builder.unpublished_changes': 'Unpublished changes',
    'setono_sylius_cms.builder.discard_changes': 'Discard changes',
    'setono_sylius_cms.builder.discarding': 'Discarding…',
    'setono_sylius_cms.builder.discard_failed': "Couldn't discard the changes",
    'setono_sylius_cms.builder.blocks': 'Blocks',
    'setono_sylius_cms.builder.add_block': 'Add block',
    'setono_sylius_cms.builder.add_block_inside': 'Add a block inside %block%',
    'setono_sylius_cms.builder.search_block_types': 'Search block types',
    'setono_sylius_cms.builder.no_matching_block_types': 'No block types match',
    'setono_sylius_cms.builder.drop_refused': "%block% can't go there",
    'setono_sylius_cms.builder.children': 'Blocks inside',
    'setono_sylius_cms.builder.delete_block': 'Delete block',
    'setono_sylius_cms.builder.no_blocks': 'No blocks yet',
    'setono_sylius_cms.builder.no_selection': 'Select a block to edit its fields',
    'setono_sylius_cms.builder.unsupported_field_type': "This field type isn't supported yet",
    'setono_sylius_cms.builder.preview': 'Preview',
    'setono_sylius_cms.builder.preview_failed': "Couldn't load the preview",
    'setono_sylius_cms.builder.preview_channel': 'Preview channel',
    'setono_sylius_cms.builder.preview_width': 'Preview width',
    'setono_sylius_cms.builder.desktop': 'Desktop',
    'setono_sylius_cms.builder.mobile': 'Mobile',
};

export const registry: Registry = { blockTypes, contentTypes: [page], strings };

export function block(key: string, fields: FieldValues = {}): StoredBlock {
    return blockOf('heading', key, [], fields);
}

/** A block of any block type, with children. */
export function blockOf(type: string, key: string, children: StoredBlock[] = [], fields: FieldValues = {}): StoredBlock {
    return { key, type, version: 1, style: null, fields, translated: {}, conditions: [], children };
}

export function entry(blocks: StoredBlock[] = []): Entry {
    return {
        uuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
        contentType: 'page',
        version: 1,
        createdAt: '2026-11-14T10:00:00Z',
        fields: {},
        translations: {
            en_US: { shown: true, fields: { slug: 'about-us', title: 'About us' }, translated: {} },
        },
        tree: {
            layout: { code: 'standard', version: 1, fields: {}, translated: {} },
            blocks,
        },
    };
}

/** A store on an entry with the given blocks, whose new block keys are new-1, new-2 and so on. */
export function storeWith(blocks: StoredBlock[] = [], types: BlockTypeDefinition[] = blockTypes): BuilderStore {
    let count = 0;

    return createBuilderStore({
        locale: 'en_US',
        blockTypes: types,
        contentTypes: [page],
        entry: entry(blocks),
        status: 'never_published',
        generateKey: () => `new-${++count}`,
    });
}

export function keys(store: BuilderStore): string[] {
    return store.getState().entry.tree.blocks.map((stored) => stored.key);
}

/** The tree's shape as nested keys, such as ['a', ['b', ['c']]] for a with b inside, and c inside b. */
export type Shape = Array<string | Shape>;

export function shape(blocks: readonly StoredBlock[]): Shape {
    return blocks.flatMap((stored) => (stored.children.length === 0 ? [stored.key] : [stored.key, shape(stored.children)]));
}
