import { render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { Change } from '../src/changeSet';
import type { BuilderConfig } from '../src/config';
import type { ContentTypeDefinition, Entry, EntryResponse, EntryStatus, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { page, registry, richTextSchema, settings } from './fixtures';

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
    timeZone: 'UTC',
};

/** The FAQ's items: no URL of their own and no block tree, with a translatable answer and their categories. */
const faqItem: ContentTypeDefinition = {
    code: 'faq_item',
    label: 'FAQ item',
    blocks: null,
    prefix: null,
    layouts: [],
    fields: [
        {
            name: 'categories',
            schema: {
                type: 'array',
                items: { type: 'string', minLength: 1 },
                'x-cms-type': 'entries',
                'x-cms-label': 'Categories',
                'x-cms-help': null,
                'x-cms-required': false,
                'x-cms-translatable': false,
                'x-cms-options': { max: null, sources: false, limit: 8, contentType: 'faq_category' },
            },
        },
        { name: 'answer', schema: { ...richTextSchema, 'x-cms-label': 'Answer' } },
    ],
};

function shippingQuestion(): Entry {
    return {
        uuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
        contentType: 'faq_item',
        version: 1,
        createdAt: '2026-11-19T10:00:00Z',
        fields: {},
        translations: {
            en_US: {
                shown: true,
                fields: { title: 'How fast do you ship?', answer: { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Within three days.' }] }] } },
                translated: {},
            },
        },
        tree: { layout: null, blocks: [] },
    };
}

function fakeApi() {
    return {
        fetchRegistry: vi.fn(async () => ({
            ...registry,
            contentTypes: [page, faqItem],
            strings: { ...registry.strings, 'setono_sylius_cms.builder.no_preview': 'This entry has no page of its own to preview.' },
        })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: shippingQuestion(), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (_changes: readonly Change[]): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: shippingQuestion(), settings: settings(), status: 'never_published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(() => Promise.reject(new Error('The entry has no URL in en_US'))),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    };
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

describe('an entry without a URL of its own and without a block tree, such as a FAQ item', () => {
    it('should say it has no page to preview, without asking for one', async () => {
        const api = fakeApi();
        await openBuilder(api);

        expect(await screen.findByText('This entry has no page of its own to preview.')).toBeInTheDocument();
        expect(screen.queryByTitle('Preview')).not.toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        expect(api.createPreviewUrl).not.toHaveBeenCalled();
    });

    it('should be edited in its settings, with the tree pane hidden', async () => {
        await openBuilder(fakeApi());

        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
        const settingsPanel = within(within(screen.getByRole('complementary')).getByRole('tabpanel', { name: 'Settings' }));
        await waitFor(() => expect(settingsPanel.getByRole('textbox', { name: 'Answer' })).toHaveTextContent('Within three days.'));
        expect(settingsPanel.getByRole('button', { name: 'Categories' })).toBeInTheDocument();
        expect(within(screen.getByRole('complementary')).queryByRole('tab', { name: 'URL & SEO' })).not.toBeInTheDocument();
    });
});
