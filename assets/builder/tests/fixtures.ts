import { createBuilderStore, type BuilderStore } from '../src/store';
import type { BlockTypeDefinition, Entry, EntrySettings, FieldValues, Registry, StoredBlock } from '../src/types';

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
    'setono_sylius_cms.builder.unpublished_changes': 'Unpublished changes',
    'setono_sylius_cms.builder.discard_changes': 'Discard changes',
    'setono_sylius_cms.builder.discarding': 'Discarding…',
    'setono_sylius_cms.builder.discard_failed': "Couldn't discard the changes",
    'setono_sylius_cms.builder.blocks': 'Blocks',
    'setono_sylius_cms.builder.add_block': 'Add block',
    'setono_sylius_cms.builder.delete_block': 'Delete block',
    'setono_sylius_cms.builder.no_blocks': 'No blocks yet',
    'setono_sylius_cms.builder.entry_settings': 'Entry settings',
    'setono_sylius_cms.builder.settings': 'Settings',
    'setono_sylius_cms.builder.takes_effect_on_save': 'Takes effect when saved, without publishing',
    'setono_sylius_cms.builder.enabled': 'Enabled',
    'setono_sylius_cms.builder.date_window': 'Date window',
    'setono_sylius_cms.builder.show_from': 'Show from',
    'setono_sylius_cms.builder.show_until': 'Show until',
    'setono_sylius_cms.builder.channels': 'Channels',
    'setono_sylius_cms.builder.audience': 'Audience',
    'setono_sylius_cms.builder.audience_everyone': 'Everyone',
    'setono_sylius_cms.builder.audience_only': 'Only these',
    'setono_sylius_cms.builder.audience_except': 'Everyone except these',
    'setono_sylius_cms.builder.customer_groups': 'Customer groups',
    'setono_sylius_cms.builder.guests': 'Guests',
    'setono_sylius_cms.builder.no_group': 'Signed in without a group',
    'setono_sylius_cms.builder.deleted': 'deleted',
    'setono_sylius_cms.builder.unsupported_field_type': "This field type isn't supported yet",
    'setono_sylius_cms.builder.preview': 'Preview',
    'setono_sylius_cms.builder.preview_failed': "Couldn't load the preview",
    'setono_sylius_cms.builder.preview_channel': 'Preview channel',
    'setono_sylius_cms.builder.preview_width': 'Preview width',
    'setono_sylius_cms.builder.desktop': 'Desktop',
    'setono_sylius_cms.builder.mobile': 'Mobile',
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

/** The settings of a new entry in the WEB channel, changed by the overrides. */
export function settings(overrides: Partial<EntrySettings> = {}): EntrySettings {
    return { enabled: true, showFrom: null, showUntil: null, channels: ['WEB'], audience: { mode: 'everyone', groups: [] }, ...overrides };
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
