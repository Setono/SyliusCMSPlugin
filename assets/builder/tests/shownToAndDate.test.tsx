import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { block, entry, registry, settings } from './fixtures';

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
    customerGroups: [
        { code: 'retail', name: 'Retail' },
        { code: 'wholesale', name: 'Wholesale' },
    ],
    timeZone: 'UTC',
};

/** Answers each preview request with a new URL that says what it shows. */
function previewUrls() {
    let count = 0;

    return vi.fn(async ({ shownTo, date }: PreviewRequest) => `http://admin.example/en_US/about-us?shownTo=${shownTo}&date=${date ?? 'now'}&_cms_preview=${++count}`);
}

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings({ channels: ['WEB'] }), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(), settings: settings({ channels: ['WEB'] }), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings({ channels: ['WEB'] })),
        createPreviewUrl: previewUrls(),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi = fakeApi(), builderConfig: BuilderConfig = config) {
    render(<App config={builderConfig} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function preview(): HTMLIFrameElement {
    return screen.getByTitle('Preview') as HTMLIFrameElement;
}

function shownTo(): HTMLSelectElement {
    return screen.getByRole('combobox', { name: 'Shown to' }) as HTMLSelectElement;
}

function date(): HTMLInputElement {
    return screen.getByLabelText('Date') as HTMLInputElement;
}

/** The UTC date and time of a datetime-local input's value, which is in the editor's time zone. */
function utc(local: string): string {
    return new Date(local).toISOString();
}

describe('Shown to and Date', () => {
    it('should show the preview to guests now when the builder gets no starting values', async () => {
        const api = fakeApi();
        await openBuilder(api);

        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('shownTo=@guest&date=now')));
        expect(api.createPreviewUrl).toHaveBeenCalledExactlyOnceWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: null });
        expect(shownTo()).toHaveValue('@guest');
        expect(date()).toHaveValue('');
        expect(screen.getByRole('button', { name: 'Now' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('should start where the starting values say', async () => {
        const api = fakeApi();
        await openBuilder(api, { ...config, preview: { shownTo: 'wholesale', date: '2026-12-01T09:00:00Z' } });

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledExactlyOnceWith({ channel: 'WEB', locale: 'en_US', shownTo: 'wholesale', date: '2026-12-01T09:00:00Z' }));
        expect(shownTo()).toHaveValue('wholesale');
        expect(utc(date().value)).toBe('2026-12-01T09:00:00.000Z');
        expect(screen.getByRole('button', { name: 'Now' })).toHaveAttribute('aria-pressed', 'false');
    });

    it("should offer guests, customers signed in without a group and the store's customer groups", async () => {
        await openBuilder();

        expect(Array.from(shownTo().options, (option) => [option.value, option.text])).toEqual([
            ['@guest', 'Guest'],
            ['@no_group', 'Signed in without a group'],
            ['retail', 'Retail'],
            ['wholesale', 'Wholesale'],
        ]);
    });

    it('should reload the preview shown to the value picked', async () => {
        const api = fakeApi();
        await openBuilder(api);
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('_cms_preview=1')));

        fireEvent.change(shownTo(), { target: { value: 'wholesale' } });

        expect(shownTo()).toHaveValue('wholesale');
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('shownTo=wholesale&date=now&_cms_preview=2')));
        expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'en_US', shownTo: 'wholesale', date: null });
    });

    it('should reload the preview at the date and time picked once the editor leaves the input', async () => {
        const api = fakeApi();
        await openBuilder(api);
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('_cms_preview=1')));

        fireEvent.change(date(), { target: { value: '2026-12-24T18:30' } });
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);
        fireEvent.blur(date());

        const picked = utc('2026-12-24T18:30').replace('.000Z', 'Z');
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining(`date=${picked}&_cms_preview=2`)));
        expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: picked });
        expect(screen.getByRole('button', { name: 'Now' })).toHaveAttribute('aria-pressed', 'false');
    });

    it('should reload the preview at the date and time picked on Enter', async () => {
        const api = fakeApi();
        await openBuilder(api);
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(1));

        fireEvent.change(date(), { target: { value: '2026-12-24T18:30' } });
        fireEvent.keyDown(date(), { key: 'Enter' });

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: utc('2026-12-24T18:30').replace('.000Z', 'Z') }));
    });

    it('should not reload when the editor leaves the date as it was', async () => {
        const api = fakeApi();
        await openBuilder(api, { ...config, preview: { shownTo: '@guest', date: '2026-12-01T09:00:00Z' } });
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(1));

        fireEvent.blur(date());
        fireEvent.keyDown(date(), { key: 'Enter' });

        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);
    });

    it('should show the page now again with Now, and when the editor empties the date', async () => {
        const api = fakeApi();
        await openBuilder(api, { ...config, preview: { shownTo: '@guest', date: '2026-12-01T09:00:00Z' } });
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(1));

        fireEvent.click(screen.getByRole('button', { name: 'Now' }));

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: null }));
        expect(date()).toHaveValue('');
        expect(screen.getByRole('button', { name: 'Now' })).toHaveAttribute('aria-pressed', 'true');

        fireEvent.change(date(), { target: { value: '2026-12-24T18:30' } });
        fireEvent.blur(date());
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(3));
        fireEvent.change(date(), { target: { value: '' } });
        fireEvent.blur(date());

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(4));
        expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'en_US', shownTo: '@guest', date: null });
    });
});
