import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, PresenceResponse, SaveResponse } from '../src/types';
import { block, entry, registry, settings } from './fixtures';

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    endpoints: {
        registry: '/registry',
        entry: '/entry',
        publish: '/publish',
        discard: '/discard',
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [
        { code: 'WEB', name: 'Web store' },
        { code: 'B2B', name: 'Wholesale' },
    ],
    customerGroups: [],
};

/** Answers each preview request with a new URL: …?_cms_preview=1, then 2 and so on. */
function previewUrls() {
    let count = 0;

    return vi.fn(async ({ channel, locale }: PreviewRequest) => `http://admin.example/${locale}/about-us?channel=${channel}&_cms_preview=${++count}`);
}

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings({ channels: ['WEB', 'B2B'] }), status: 'never_published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'never_published', lockToken: 1 })),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings({ channels: ['WEB', 'B2B'] }), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings({ channels: ['WEB', 'B2B'] })),
        createPreviewUrl: previewUrls(),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
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

function deferred<T>() {
    let resolve: (value: T) => void = () => {};
    const promise = new Promise<T>((settle) => {
        resolve = settle;
    });

    return { promise, resolve };
}

describe('the preview', () => {
    it("should show the entry's shop page in its first channel and the edited locale", async () => {
        const api = fakeApi();
        await openBuilder(api);

        await waitFor(() => expect(preview()).toHaveAttribute('src', 'http://admin.example/en_US/about-us?channel=WEB&_cms_preview=1'));
        expect(api.createPreviewUrl).toHaveBeenCalledExactlyOnceWith({ channel: 'WEB', locale: 'en_US' });
        expect(screen.getByRole('combobox', { name: 'Preview channel' })).toHaveValue('WEB');
    });

    it("should not reload after a saved change, which the bridge patches in instead", async () => {
        const saved = deferred<SaveResponse>();
        const api = fakeApi({ saveChanges: vi.fn(() => saved.promise) });
        await openBuilder(api);
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('_cms_preview=1')));
        const first = preview();

        fireEvent.click(screen.getByRole('button', { name: 'Delete block' }));
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledTimes(1));
        await act(async () => saved.resolve({ status: 'never_published', lockToken: 1 }));

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);
        expect(preview()).toBe(first);
    });

    it('should reload when the same URL comes back', async () => {
        const api = fakeApi({
            fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome back' } })]), settings: settings({ channels: ['WEB', 'B2B'] }), status: 'unpublished_changes', lockToken: 0, editors: [] })),
            createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=same'),
        });
        await openBuilder(api);
        await waitFor(() => expect(preview()).toHaveAttribute('src', 'http://admin.example/en_US/about-us?_cms_preview=same'));
        const first = preview();

        fireEvent.click(screen.getByRole('button', { name: 'Discard changes' }));

        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(preview()).not.toBe(first));
    });

    it('should reload with the published content after discarding changes', async () => {
        const api = fakeApi({
            fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome back' } })]), settings: settings({ channels: ['WEB', 'B2B'] }), status: 'unpublished_changes', lockToken: 0, editors: [] })),
        });
        await openBuilder(api);
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('_cms_preview=1')));
        const first = preview();

        fireEvent.click(screen.getByRole('button', { name: 'Discard changes' }));

        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('_cms_preview=2')));
        expect(preview()).not.toBe(first);
        expect(api.discard).toHaveBeenCalledTimes(1);
        expect(api.saveChanges).not.toHaveBeenCalled();
    });

    it('should show the preview in the channel picked', async () => {
        const api = fakeApi();
        await openBuilder(api);
        const picker = screen.getByRole('combobox', { name: 'Preview channel' });

        expect(Array.from((picker as HTMLSelectElement).options, (option) => [option.value, option.text])).toEqual([
            ['WEB', 'Web store'],
            ['B2B', 'Wholesale'],
        ]);

        fireEvent.change(picker, { target: { value: 'B2B' } });

        expect(picker).toHaveValue('B2B');
        await waitFor(() => expect(preview()).toHaveAttribute('src', expect.stringContaining('channel=B2B')));
        expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'B2B', locale: 'en_US' });
    });

    it('should show the latest preview asked for when the answers come back out of order', async () => {
        const web = deferred<string>();
        const b2b = deferred<string>();
        const api = fakeApi({ createPreviewUrl: vi.fn(({ channel }: PreviewRequest) => (channel === 'WEB' ? web.promise : b2b.promise)) });
        await openBuilder(api);

        fireEvent.change(screen.getByRole('combobox', { name: 'Preview channel' }), { target: { value: 'B2B' } });
        await act(async () => b2b.resolve('http://admin.example/en_US/about-us?channel=B2B'));
        await act(async () => web.resolve('http://admin.example/en_US/about-us?channel=WEB'));

        expect(preview()).toHaveAttribute('src', 'http://admin.example/en_US/about-us?channel=B2B');
    });

    it('should switch between desktop and mobile width', async () => {
        await openBuilder();
        const desktop = screen.getByRole('button', { name: 'Desktop' });
        const mobile = screen.getByRole('button', { name: 'Mobile' });

        expect(screen.getByRole('group', { name: 'Preview width' })).toContainElement(desktop);
        expect(desktop).toHaveAttribute('aria-pressed', 'true');
        expect(mobile).toHaveAttribute('aria-pressed', 'false');
        expect(screen.getByRole('main')).toHaveAttribute('data-width', 'desktop');

        fireEvent.click(mobile);

        expect(desktop).toHaveAttribute('aria-pressed', 'false');
        expect(mobile).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByRole('main')).toHaveAttribute('data-width', 'mobile');
    });

    it("should say so when the preview can't be loaded", async () => {
        await openBuilder(fakeApi({ createPreviewUrl: vi.fn(() => Promise.reject(new Error('The entry has no URL in en_US'))) }));

        expect(await screen.findByRole('alert')).toHaveTextContent("Couldn't load the preview");
        expect(screen.queryByTitle('Preview')).not.toBeInTheDocument();
    });

    it('should show no preview for an entry without channels', async () => {
        const api = fakeApi({
            fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(), settings: settings({ channels: [] }), status: 'never_published', lockToken: 0, editors: [] })),
        });
        await openBuilder(api);

        expect(screen.queryByTitle('Preview')).not.toBeInTheDocument();
        expect(screen.queryByRole('combobox', { name: 'Preview channel' })).not.toBeInTheDocument();
        expect(api.createPreviewUrl).not.toHaveBeenCalled();
    });
});
