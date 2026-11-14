import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ApiError, type BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntrySettings, EntryStatus, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { block, entry, registry, settings } from './fixtures';

const config: BuilderConfig = {
    entryUuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
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
    channels: [
        { code: 'WEB', name: 'Web store' },
        { code: 'B2B', name: 'Wholesale store' },
    ],
    customerGroups: [
        { code: 'retail', name: 'Retail' },
        { code: 'wholesale', name: 'Wholesale' },
    ],
};

/** The server saves what it's sent onto what it has, and answers with all of it. */
function fakeApi(initial: EntrySettings = settings(), overrides: Partial<BuilderApi> = {}) {
    let saved = initial;

    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: initial, status: 'published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'published', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(), settings: saved, status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async (changes: Partial<EntrySettings>): Promise<EntrySettings> => {
            saved = { ...saved, ...changes };

            return saved;
        }),
        createPreviewUrl: vi.fn(async ({ channel }: { channel: string }) => `http://admin.example/en_US/about-us?channel=${channel}`),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi = fakeApi(), builderConfig: BuilderConfig = config) {
    render(<App config={builderConfig} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function pane() {
    return within(screen.getByRole('complementary'));
}

async function saved() {
    await waitFor(() => expect(pane().getByText('Saved')).toBeInTheDocument());
}

describe('the entry settings', () => {
    it('should show the settings in a Settings tab while no block is selected', async () => {
        await openBuilder();

        expect(pane().getByRole('tab', { name: 'Settings', selected: true })).toBeInTheDocument();
        const panel = within(pane().getByRole('tabpanel', { name: 'Settings' }));
        expect(panel.getByRole('checkbox', { name: 'Enabled' })).toBeChecked();
        expect(panel.getByLabelText('Show from')).toHaveValue('');
        expect(panel.getByLabelText('Show until')).toHaveValue('');
        expect(panel.getByRole('group', { name: 'Channels' })).toBeInTheDocument();
        expect(panel.getByRole('combobox', { name: 'Audience' })).toHaveValue('everyone');
    });

    it('should say beside each setting that it takes effect on save', async () => {
        await openBuilder();
        const panel = within(pane().getByRole('tabpanel', { name: 'Settings' }));

        for (const setting of [
            panel.getByRole('checkbox', { name: 'Enabled' }),
            panel.getByRole('group', { name: 'Date window' }),
            panel.getByRole('group', { name: 'Channels' }),
            panel.getByRole('combobox', { name: 'Audience' }),
        ]) {
            expect(setting).toHaveAccessibleDescription('Takes effect when saved, without publishing');
        }
    });

    it('should select the entry settings at the top of the tree', async () => {
        await openBuilder();
        const entrySettings = screen.getByRole('button', { name: 'Entry settings' });
        expect(entrySettings).toHaveAttribute('aria-pressed', 'true');

        fireEvent.click(screen.getByRole('button', { name: 'Heading Welcome' }));

        expect(entrySettings).toHaveAttribute('aria-pressed', 'false');
        expect(pane().queryByRole('tab', { name: 'Settings' })).not.toBeInTheDocument();

        fireEvent.click(entrySettings);

        expect(entrySettings).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByRole('button', { name: 'Heading Welcome', pressed: false })).toBeInTheDocument();
        expect(pane().getByRole('tab', { name: 'Settings' })).toBeInTheDocument();
    });

    it('should save enabled at once, without touching the draft', async () => {
        const api = fakeApi();
        await openBuilder(api);

        fireEvent.click(pane().getByRole('checkbox', { name: 'Enabled' }));

        expect(pane().getByRole('checkbox', { name: 'Enabled' })).not.toBeChecked();
        expect(api.saveSettings).toHaveBeenCalledExactlyOnceWith({ enabled: false });
        await saved();
        expect(api.saveChanges).not.toHaveBeenCalled();
        expect(screen.getByText('Published', { exact: true })).toBeInTheDocument();
    });

    it('should save the date window in UTC when a date is entered', async () => {
        const api = fakeApi();
        await openBuilder(api);
        const showFrom = pane().getByLabelText('Show from');
        const local = '2026-11-27T09:30';

        fireEvent.change(showFrom, { target: { value: local } });
        expect(api.saveSettings).not.toHaveBeenCalled();
        fireEvent.blur(showFrom);

        expect(api.saveSettings).toHaveBeenCalledExactlyOnceWith({ showFrom: new Date(local).toISOString().replace('.000Z', 'Z') });
        await saved();
        expect(showFrom).toHaveValue(local);

        fireEvent.change(showFrom, { target: { value: '' } });
        fireEvent.blur(showFrom);

        expect(api.saveSettings).toHaveBeenLastCalledWith({ showFrom: null });
    });

    it('should show the saved date window in local time', async () => {
        await openBuilder(fakeApi(settings({ showUntil: '2026-12-01T00:00:00Z' })));

        const until = new Date('2026-12-01T00:00:00Z');
        const pad = (value: number) => String(value).padStart(2, '0');
        expect(pane().getByLabelText('Show until')).toHaveValue(
            `${until.getFullYear()}-${pad(until.getMonth() + 1)}-${pad(until.getDate())}T${pad(until.getHours())}:${pad(until.getMinutes())}`,
        );
    });

    it("should not save a date that didn't change", async () => {
        const api = fakeApi();
        await openBuilder(api);

        fireEvent.blur(pane().getByLabelText('Show until'));

        expect(api.saveSettings).not.toHaveBeenCalled();
    });

    it('should say why the server refused a setting, and show what it has', async () => {
        const api = fakeApi(settings({ showFrom: '2026-12-01T00:00:00Z' }), {
            saveSettings: vi.fn(() => Promise.reject(new ApiError('"Show until" must come after "Show from"', 400))),
        });
        await openBuilder(api);
        const showUntil = pane().getByLabelText('Show until');

        fireEvent.change(showUntil, { target: { value: '2026-11-01T00:00' } });
        fireEvent.blur(showUntil);

        await waitFor(() => expect(pane().getByText("Couldn't save: \"Show until\" must come after \"Show from\"")).toBeInTheDocument());
        expect(showUntil).toHaveValue('');
    });

    it("should pick the entry's channels from the store's", async () => {
        const api = fakeApi();
        await openBuilder(api);
        const channels = within(pane().getByRole('group', { name: 'Channels' }));

        expect(channels.getByRole('checkbox', { name: 'Web store' })).toBeChecked();
        expect(channels.getByRole('checkbox', { name: 'Wholesale store' })).not.toBeChecked();

        fireEvent.click(channels.getByRole('checkbox', { name: 'Wholesale store' }));

        expect(api.saveSettings).toHaveBeenLastCalledWith({ channels: ['WEB', 'B2B'] });
        await saved();

        fireEvent.click(channels.getByRole('checkbox', { name: 'Web store' }));

        expect(api.saveSettings).toHaveBeenLastCalledWith({ channels: ['B2B'] });
    });

    it('should hide the channel picker in a store with one channel', async () => {
        await openBuilder(fakeApi(), { ...config, channels: [{ code: 'WEB', name: 'Web store' }] });

        expect(pane().queryByRole('group', { name: 'Channels' })).not.toBeInTheDocument();
        expect(pane().getByRole('checkbox', { name: 'Enabled' })).toBeInTheDocument();
    });

    it('should preview the entry in its own channels only, and follow them', async () => {
        await openBuilder();
        const picker = screen.getByRole('combobox', { name: 'Preview channel' }) as HTMLSelectElement;

        expect(Array.from(picker.options, (option) => option.value)).toEqual(['WEB']);

        fireEvent.click(within(pane().getByRole('group', { name: 'Channels' })).getByRole('checkbox', { name: 'Wholesale store' }));

        expect(Array.from(picker.options, (option) => option.value)).toEqual(['WEB', 'B2B']);
        expect(picker).toHaveValue('WEB');

        fireEvent.click(within(pane().getByRole('group', { name: 'Channels' })).getByRole('checkbox', { name: 'Web store' }));

        expect(Array.from(picker.options, (option) => option.value)).toEqual(['B2B']);
        expect(picker).toHaveValue('B2B');
        await waitFor(() => expect(screen.getByTitle('Preview')).toHaveAttribute('src', expect.stringContaining('channel=B2B')));
    });

    it('should limit the audience to some customer groups', async () => {
        const api = fakeApi();
        await openBuilder(api);

        expect(pane().queryByRole('group', { name: 'Customer groups' })).not.toBeInTheDocument();

        fireEvent.change(pane().getByRole('combobox', { name: 'Audience' }), { target: { value: 'only' } });

        await waitFor(() => expect(api.saveSettings).toHaveBeenLastCalledWith({ audience: { mode: 'only', groups: [] } }));
        const groups = within(pane().getByRole('group', { name: 'Customer groups' }));
        expect(groups.getAllByRole('checkbox').map((checkbox) => (checkbox as HTMLInputElement).labels?.[0]?.textContent)).toEqual([
            'Guests',
            'Signed in without a group',
            'Retail',
            'Wholesale',
        ]);

        fireEvent.click(groups.getByRole('checkbox', { name: 'Wholesale' }));
        await waitFor(() => expect(api.saveSettings).toHaveBeenLastCalledWith({ audience: { mode: 'only', groups: ['wholesale'] } }));

        fireEvent.click(groups.getByRole('checkbox', { name: 'Guests' }));
        await waitFor(() => expect(api.saveSettings).toHaveBeenLastCalledWith({ audience: { mode: 'only', groups: ['wholesale', '@guest'] } }));

        fireEvent.change(pane().getByRole('combobox', { name: 'Audience' }), { target: { value: 'except' } });
        await waitFor(() => expect(api.saveSettings).toHaveBeenLastCalledWith({ audience: { mode: 'except', groups: ['wholesale', '@guest'] } }));

        fireEvent.change(pane().getByRole('combobox', { name: 'Audience' }), { target: { value: 'everyone' } });
        await waitFor(() => expect(api.saveSettings).toHaveBeenLastCalledWith({ audience: { mode: 'everyone', groups: [] } }));
        expect(pane().queryByRole('group', { name: 'Customer groups' })).not.toBeInTheDocument();
        await saved();
    });

    it('should name a deleted customer group by its code, so it can be taken out', async () => {
        const api = fakeApi(settings({ audience: { mode: 'only', groups: ['gone', 'wholesale'] } }));
        await openBuilder(api);
        const groups = within(pane().getByRole('group', { name: 'Customer groups' }));

        expect(groups.getByRole('checkbox', { name: 'Wholesale' })).toBeChecked();
        expect(groups.getByRole('checkbox', { name: 'gone (deleted)' })).toBeChecked();

        fireEvent.click(groups.getByRole('checkbox', { name: 'gone (deleted)' }));

        expect(api.saveSettings).toHaveBeenLastCalledWith({ audience: { mode: 'only', groups: ['wholesale'] } });
    });
});
