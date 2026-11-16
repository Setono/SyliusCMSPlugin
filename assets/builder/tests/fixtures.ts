import { createBuilderStore, type BuilderStore } from '../src/store';
import type { AllowedBlockType, BlockTypeDefinition, ConditionKindDefinition, ContentTypeDefinition, Entry, EntrySettings, FieldSchema, FieldValues, LayoutDefinition, Registry, StoredBlock } from '../src/types';

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
    styles: [],
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
    styles: [
        { code: 'muted', label: 'Muted' },
        { code: 'dark', label: 'Dark' },
    ],
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
    styles: [],
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
    styles: [],
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
    styles: [],
    fields: [],
};

export const blockTypes: BlockTypeDefinition[] = [group, columns, column, spacer, heading];

/** A rich text field's schema, as the registry endpoint answers it. */
export const richTextSchema: FieldSchema = {
    type: 'object',
    properties: { type: { const: 'doc' }, content: { type: 'array' } },
    required: ['type', 'content'],
    'x-cms-type': 'rich_text',
    'x-cms-label': 'Content',
    'x-cms-help': null,
    'x-cms-required': false,
    'x-cms-translatable': true,
};

export const accordion: BlockTypeDefinition = {
    code: 'accordion',
    label: 'Accordion',
    version: 1,
    thumbnail: '/thumbnail/accordion.svg',
    disabled: false,
    parents: null,
    children: [{ type: 'accordion_item', min: 1, max: null }],
    styles: [],
    fields: [],
};

export const accordionItem: BlockTypeDefinition = {
    code: 'accordion_item',
    label: 'Accordion item',
    version: 1,
    thumbnail: '/thumbnail/accordion_item.svg',
    disabled: false,
    parents: ['accordion'],
    children: [],
    styles: [],
    fields: [
        {
            name: 'title',
            schema: {
                type: 'string',
                'x-cms-type': 'text',
                'x-cms-label': 'Title',
                'x-cms-help': null,
                'x-cms-required': true,
                'x-cms-translatable': true,
                'x-cms-options': { maxLength: null },
            },
        },
        { name: 'content', schema: richTextSchema },
    ],
};

/** Page's default layout, which prints the title unless Show title is off. */
export const standard: LayoutDefinition = {
    code: 'standard',
    label: 'Standard',
    version: 1,
    thumbnail: '/thumbnail/layout/standard.svg',
    default: true,
    disabled: false,
    shows: ['title'],
    fields: [
        {
            name: 'showTitle',
            schema: {
                type: 'boolean',
                default: true,
                'x-cms-type': 'toggle',
                'x-cms-label': 'Show title',
                'x-cms-help': null,
                'x-cms-required': false,
                'x-cms-translatable': false,
            },
        },
    ],
};

/** A project's layout for pages, which doesn't print the title: a hero with a tagline, and a header that can be transparent. */
export const landing: LayoutDefinition = {
    code: 'landing',
    label: 'Landing',
    version: 2,
    thumbnail: null,
    default: false,
    disabled: false,
    shows: [],
    fields: [
        {
            name: 'tagline',
            schema: {
                type: 'string',
                'x-cms-type': 'text',
                'x-cms-label': 'Tagline',
                'x-cms-help': null,
                'x-cms-required': false,
                'x-cms-translatable': true,
                'x-cms-options': { maxLength: null },
            },
        },
        {
            name: 'transparentHeader',
            schema: {
                type: 'boolean',
                default: false,
                'x-cms-type': 'toggle',
                'x-cms-label': 'Transparent header',
                'x-cms-help': null,
                'x-cms-required': false,
                'x-cms-translatable': false,
            },
        },
    ],
};

export const page: ContentTypeDefinition = { code: 'page', label: 'Page', blocks: anyBlock, homepage: true, layouts: [standard, landing] };

export const strings: Record<string, string> = {
    'setono_sylius_cms.builder.layout': 'Layout',
    'setono_sylius_cms.builder.layout_removed': 'Its layout "%layout%" was removed from the code or disabled, so it shows in the default layout, %default%, until you pick one.',
    'setono_sylius_cms.builder.not_shown_in_layout': 'Not shown in this layout',
    'setono_sylius_cms.builder.loading': 'Loading…',
    'setono_sylius_cms.builder.back': 'Back',
    'setono_sylius_cms.builder.saving': 'Saving…',
    'setono_sylius_cms.builder.saved': 'Saved',
    'setono_sylius_cms.builder.save_failed': "Couldn't save",
    'setono_sylius_cms.builder.save_stale': "Colleagues' changes can't be merged with yours",
    'setono_sylius_cms.builder.reload': 'Reload',
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
    'setono_sylius_cms.builder.add_block_above': 'Add block above',
    'setono_sylius_cms.builder.add_block_below': 'Add block below',
    'setono_sylius_cms.builder.select_parent': 'Select parent',
    'setono_sylius_cms.builder.block_settings': 'Settings',
    'setono_sylius_cms.builder.search_block_types': 'Search block types',
    'setono_sylius_cms.builder.no_matching_block_types': 'No block types match',
    'setono_sylius_cms.builder.drop_refused': "%block% can't go there",
    'setono_sylius_cms.builder.children': 'Blocks inside',
    'setono_sylius_cms.builder.delete_block': 'Delete block',
    'setono_sylius_cms.builder.duplicate': 'Duplicate',
    'setono_sylius_cms.builder.move_up': 'Move up',
    'setono_sylius_cms.builder.move_down': 'Move down',
    'setono_sylius_cms.builder.undo': 'Undo',
    'setono_sylius_cms.builder.redo': 'Redo',
    'setono_sylius_cms.builder.path': 'Path',
    'setono_sylius_cms.builder.block_style': 'Style',
    'setono_sylius_cms.builder.no_style': 'None',
    'setono_sylius_cms.builder.blocks_selected': '%count% blocks selected',
    'setono_sylius_cms.builder.no_blocks': 'No blocks yet',
    'setono_sylius_cms.builder.entry_settings': 'Entry settings',
    'setono_sylius_cms.builder.settings': 'Settings',
    'setono_sylius_cms.builder.more': 'More',
    'setono_sylius_cms.builder.url_and_seo': 'URL & SEO',
    'setono_sylius_cms.builder.slug': 'Slug',
    'setono_sylius_cms.builder.slug_help': 'Generated from the title until you change it. The URL: %url%',
    'setono_sylius_cms.builder.slug_help_none': 'Generated from the title until you change it.',
    'setono_sylius_cms.builder.old_urls': 'Redirects from',
    'setono_sylius_cms.builder.old_urls_help': 'The URLs of the slugs this had before, which redirect here.',
    'setono_sylius_cms.builder.more_help': "Publishing history, revisions, share links and placements are on the entry's overview page.",
    'setono_sylius_cms.builder.open_overview': 'Open the overview page',
    'setono_sylius_cms.builder.takes_effect_on_save': 'Takes effect when saved, without publishing',
    'setono_sylius_cms.builder.enabled': 'Enabled',
    'setono_sylius_cms.builder.date_window': 'Date window',
    'setono_sylius_cms.builder.show_from': 'Show from',
    'setono_sylius_cms.builder.show_until': 'Show until',
    'setono_sylius_cms.builder.channels': 'Channels',
    'setono_sylius_cms.builder.homepage_of': 'Homepage of',
    'setono_sylius_cms.builder.homepage_of_help': "It shows at each of these channels' root URL in place of the store's own homepage.",
    'setono_sylius_cms.builder.audience': 'Audience',
    'setono_sylius_cms.builder.audience_everyone': 'Everyone',
    'setono_sylius_cms.builder.audience_only': 'Only these',
    'setono_sylius_cms.builder.audience_except': 'Everyone except these',
    'setono_sylius_cms.builder.customer_groups': 'Customer groups',
    'setono_sylius_cms.builder.guests': 'Guests',
    'setono_sylius_cms.builder.no_group': 'Signed in without a group',
    'setono_sylius_cms.builder.deleted': 'deleted',
    'setono_sylius_cms.builder.unsupported_field_type': "This field type isn't supported yet",
    'setono_sylius_cms.builder.has_errors': 'Has errors',
    'setono_sylius_cms.builder.unknown_block_type': 'Unknown block type: %type%',
    'setono_sylius_cms.builder.unknown_block_type_help': "The code no longer has this block type. The block is kept as it is, but the shop doesn't show it.",
    'setono_sylius_cms.builder.preview': 'Preview',
    'setono_sylius_cms.builder.preview_failed': "Couldn't load the preview",
    'setono_sylius_cms.builder.preview_channel': 'Preview channel',
    'setono_sylius_cms.builder.preview_width': 'Preview width',
    'setono_sylius_cms.builder.shown_to': 'Shown to',
    'setono_sylius_cms.builder.shown_to_guest': 'Guest',
    'setono_sylius_cms.builder.preview_date': 'Date',
    'setono_sylius_cms.builder.preview_now': 'Now',
    'setono_sylius_cms.builder.preview_now_help': 'Shows the page as it is now. Pick a date and time to see it as it shows then.',
    'setono_sylius_cms.builder.desktop': 'Desktop',
    'setono_sylius_cms.builder.mobile': 'Mobile',
    'setono_sylius_cms.builder.rich_text.toolbar': 'Formatting',
    'setono_sylius_cms.builder.rich_text.bold': 'Bold',
    'setono_sylius_cms.builder.rich_text.italic': 'Italic',
    'setono_sylius_cms.builder.rich_text.underline': 'Underline',
    'setono_sylius_cms.builder.rich_text.strike': 'Strikethrough',
    'setono_sylius_cms.builder.rich_text.code': 'Code',
    'setono_sylius_cms.builder.rich_text.heading': 'Heading %level%',
    'setono_sylius_cms.builder.rich_text.bullet_list': 'Bulleted list',
    'setono_sylius_cms.builder.rich_text.ordered_list': 'Numbered list',
    'setono_sylius_cms.builder.rich_text.blockquote': 'Quote',
    'setono_sylius_cms.builder.rich_text.horizontal_rule': 'Horizontal line',
};

/** A Choice field of several values, as a condition kind's parameter. */
function choices(label: string, options: Record<string, string>): FieldSchema {
    return {
        type: 'array',
        items: { type: 'string', enum: Object.keys(options) },
        uniqueItems: true,
        default: [],
        'x-cms-type': 'choice',
        'x-cms-label': label,
        'x-cms-help': null,
        'x-cms-required': false,
        'x-cms-translatable': false,
        'x-cms-options': { choices: options, multiple: true },
    };
}

function dateTime(label: string): FieldSchema {
    return { type: 'string', format: 'date-time', 'x-cms-type': 'date_time', 'x-cms-label': label, 'x-cms-help': null, 'x-cms-required': false, 'x-cms-translatable': false };
}

/** The shipped condition kinds, as the registry endpoint exports them. */
export const conditionKinds: ConditionKindDefinition[] = [
    { code: 'channel', label: 'Channel', fields: [{ name: 'channels', schema: choices('Channels', { WEB: 'Web store', B2B: 'Wholesale' }) }] },
    { code: 'locale', label: 'Language', fields: [{ name: 'locales', schema: choices('Languages', { en_US: 'English (United States)', da_DK: 'Danish (Denmark)' }) }] },
    {
        code: 'date_window',
        label: 'Date window',
        fields: [
            { name: 'from', schema: dateTime('From') },
            { name: 'until', schema: dateTime('Until') },
        ],
    },
    { code: 'customer_group', label: 'Customer group', fields: [{ name: 'groups', schema: choices('Customer groups', { '@guest': 'Guests', '@no_group': 'Signed in without a group', wholesale: 'Wholesale' }) }] },
];

export const registry: Registry = { blockTypes, contentTypes: [page], conditionKinds, strings };

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

/** The settings of a new entry in the WEB channel, changed by the overrides. */
export function settings(overrides: Partial<EntrySettings> = {}): EntrySettings {
    return { enabled: true, showFrom: null, showUntil: null, channels: ['WEB'], audience: { mode: 'everyone', groups: [] }, homepageOf: [], ...overrides };
}

/** A store on an entry with the given blocks, whose new block keys are new-1, new-2 and so on. */
export function storeWith(blocks: StoredBlock[] = [], types: BlockTypeDefinition[] = blockTypes, contentType: ContentTypeDefinition = page): BuilderStore {
    let count = 0;

    return createBuilderStore({
        locale: 'en_US',
        blockTypes: types,
        contentTypes: [contentType],
        conditionKinds,
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
