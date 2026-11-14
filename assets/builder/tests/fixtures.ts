import { createBuilderStore, type BuilderStore } from '../src/store';
import type { BlockTypeDefinition, Entry, FieldValues, Registry, StoredBlock } from '../src/types';

export const heading: BlockTypeDefinition = {
    code: 'heading',
    label: 'Heading',
    version: 1,
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
    'setono_sylius_cms.builder.blocks': 'Blocks',
    'setono_sylius_cms.builder.add_block': 'Add block',
    'setono_sylius_cms.builder.delete_block': 'Delete block',
    'setono_sylius_cms.builder.no_blocks': 'No blocks yet',
    'setono_sylius_cms.builder.no_selection': 'Select a block to edit its fields',
    'setono_sylius_cms.builder.unsupported_field_type': "This field type isn't supported yet",
};

export const registry: Registry = { blockTypes: [heading], strings };

export function block(key: string, fields: FieldValues = {}): StoredBlock {
    return { key, type: 'heading', version: 1, style: null, fields, translated: {}, conditions: [], children: [] };
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
export function storeWith(blocks: StoredBlock[] = []): BuilderStore {
    let count = 0;

    return createBuilderStore({
        locale: 'en_US',
        blockTypes: [heading],
        entry: entry(blocks),
        status: 'never_published',
        generateKey: () => `new-${++count}`,
    });
}

export function keys(store: BuilderStore): string[] {
    return store.getState().entry.tree.blocks.map((stored) => stored.key);
}
