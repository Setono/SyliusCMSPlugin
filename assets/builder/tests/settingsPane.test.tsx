import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, type BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntrySettings, EntryStatus, PresenceResponse, SaveResponse, ValidationError } from '../src/types';
import { block, entry, page, registry, settings } from './fixtures';

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
    channels: [
        { code: 'WEB', name: 'Web store' },
        { code: 'B2B', name: 'Wholesale store' },
    ],
    customerGroups: [
        { code: 'retail', name: 'Retail' },
        { code: 'wholesale', name: 'Wholesale' },
    ],
    // PHP's default time zone, which every date and time in the builder is entered and shown in
    timeZone: 'Europe/Copenhagen',
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

function pane() {
    return within(screen.getByRole('complementary'));
}

async function saved() {
    await waitFor(() => expect(pane().getByText('Saved')).toBeInTheDocument());
}

describe('the entry settings', () => {
    afterEach(() => {
        vi.unstubAllEnvs();
    });

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

    it("should link to the entry's overview page from a More tab, for publishing history, revisions, share links and placements", async () => {
        await openBuilder();

        fireEvent.click(pane().getByRole('tab', { name: 'More' }));

        expect(pane().getByRole('tab', { name: 'More', selected: true })).toBeInTheDocument();
        expect(pane().getByRole('tab', { name: 'Settings', selected: false })).toBeInTheDocument();
        expect(pane().queryByRole('tabpanel', { name: 'Settings' })).not.toBeInTheDocument();
        const panel = within(pane().getByRole('tabpanel', { name: 'More' }));
        expect(panel.getByText("Publishing history, revisions, share links and placements are on the entry's overview page.")).toBeInTheDocument();
        expect(panel.getByRole('link', { name: 'Open the overview page' })).toHaveAttribute('href', '/admin/cms/entry/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e');

        fireEvent.click(pane().getByRole('tab', { name: 'Settings' }));

        expect(pane().getByRole('tabpanel', { name: 'Settings' })).toBeInTheDocument();
        expect(pane().queryByRole('tabpanel', { name: 'More' })).not.toBeInTheDocument();
    });

    it("should list a snippet's placements in the More tab, without a way to change them", async () => {
        await openBuilder(fakeApi(), {
            ...config,
            placements: [
                { uuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01', slot: 'shop.footer', label: 'Every page: the footer', removed: false },
                { uuid: '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02', slot: 'shop.removed', label: 'shop.removed', removed: true },
            ],
        });

        fireEvent.click(pane().getByRole('tab', { name: 'More' }));

        const panel = within(pane().getByRole('tabpanel', { name: 'More' }));
        const placements = within(panel.getByRole('list', { name: 'Placements' }));
        expect(placements.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Every page: the footer', 'shop.removed (no longer in the code)']);
        expect(panel.getByText('Placements are managed on the overview page.')).toBeInTheDocument();
        expect(panel.queryByRole('button')).not.toBeInTheDocument();
    });

    it('should say a snippet without placements shows in no slot', async () => {
        await openBuilder(fakeApi(), { ...config, placements: [] });
        fireEvent.click(pane().getByRole('tab', { name: 'More' }));

        expect(within(pane().getByRole('tabpanel', { name: 'More' })).getByText('It shows in no slot yet. Placements are managed on the overview page.')).toBeInTheDocument();
    });

    it('should list no placements for an entry that is not a snippet', async () => {
        await openBuilder();
        fireEvent.click(pane().getByRole('tab', { name: 'More' }));

        expect(within(pane().getByRole('tabpanel', { name: 'More' })).queryByRole('heading', { name: 'Placements' })).not.toBeInTheDocument();
    });

    it('should move between the tabs with the arrow keys, which only the selected tab takes the focus for', async () => {
        await openBuilder();
        const settingsTab = pane().getByRole('tab', { name: 'Settings' });
        const moreTab = pane().getByRole('tab', { name: 'More' });
        expect(settingsTab).toHaveAttribute('tabindex', '0');
        expect(moreTab).toHaveAttribute('tabindex', '-1');

        fireEvent.keyDown(settingsTab, { key: 'ArrowRight' });

        expect(moreTab).toHaveAttribute('aria-selected', 'true');
        expect(moreTab).toHaveFocus();
        expect(moreTab).toHaveAttribute('tabindex', '0');

        // Past the last tab comes the first again
        fireEvent.keyDown(moreTab, { key: 'ArrowRight' });
        expect(settingsTab).toHaveAttribute('aria-selected', 'true');
        expect(settingsTab).toHaveFocus();

        fireEvent.keyDown(settingsTab, { key: 'ArrowLeft' });
        expect(moreTab).toHaveAttribute('aria-selected', 'true');

        fireEvent.keyDown(moreTab, { key: 'Home' });
        expect(settingsTab).toHaveAttribute('aria-selected', 'true');
        fireEvent.keyDown(settingsTab, { key: 'End' });
        expect(moreTab).toHaveAttribute('aria-selected', 'true');
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

    it('should save the date window in UTC when a date is entered, in the time zone the server gives, which it names', async () => {
        // The editor's browser is in another time zone than the server's
        vi.stubEnv('TZ', 'Asia/Tokyo');
        const api = fakeApi();
        await openBuilder(api);
        const showFrom = pane().getByLabelText('Show from');
        const local = '2026-11-27T09:30';
        expect(showFrom).toHaveAccessibleDescription('Europe/Copenhagen');

        fireEvent.change(showFrom, { target: { value: local } });
        expect(api.saveSettings).not.toHaveBeenCalled();
        fireEvent.blur(showFrom);

        // 09:30 in Copenhagen is 08:30 in UTC
        expect(api.saveSettings).toHaveBeenCalledExactlyOnceWith({ showFrom: '2026-11-27T08:30:00Z' });
        await saved();
        expect(showFrom).toHaveValue(local);

        fireEvent.change(showFrom, { target: { value: '' } });
        fireEvent.blur(showFrom);

        expect(api.saveSettings).toHaveBeenLastCalledWith({ showFrom: null });
    });

    it('should show the saved date window in the time zone the server gives, not the browser’s', async () => {
        vi.stubEnv('TZ', 'America/New_York');
        await openBuilder(fakeApi(settings({ showUntil: '2026-12-01T00:00:00Z' })));

        expect(pane().getByLabelText('Show until')).toHaveValue('2026-12-01T01:00');
        expect(pane().getByLabelText('Show until')).toHaveAccessibleDescription('Europe/Copenhagen');
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

    it('should make a page the homepage of channels, even in a store with one channel', async () => {
        const api = fakeApi();
        await openBuilder(api, { ...config, channels: [{ code: 'WEB', name: 'Web store' }] });
        const homepageOf = pane().getByRole('group', { name: 'Homepage of' });
        const web = within(homepageOf).getByRole('checkbox', { name: 'Web store' });

        expect(web).not.toBeChecked();
        expect(homepageOf).toHaveAccessibleDescription(
            "It shows at each of these channels' root URL in place of the store's own homepage. Takes effect when saved, without publishing",
        );

        fireEvent.click(web);

        expect(api.saveSettings).toHaveBeenLastCalledWith({ homepageOf: ['WEB'] });
        await saved();
        expect(web).toBeChecked();

        fireEvent.click(web);

        expect(api.saveSettings).toHaveBeenLastCalledWith({ homepageOf: [] });
        await saved();
        expect(web).not.toBeChecked();
    });

    it('should pick the channels a page is the homepage of from those it is in', async () => {
        const api = fakeApi(settings({ channels: ['WEB', 'B2B'], homepageOf: ['B2B'] }));
        await openBuilder(api);
        const homepageOf = within(pane().getByRole('group', { name: 'Homepage of' }));

        expect(homepageOf.getByRole('checkbox', { name: 'Web store' })).not.toBeChecked();
        expect(homepageOf.getByRole('checkbox', { name: 'Wholesale store' })).toBeChecked();

        fireEvent.click(homepageOf.getByRole('checkbox', { name: 'Web store' }));

        expect(api.saveSettings).toHaveBeenLastCalledWith({ homepageOf: ['B2B', 'WEB'] });
    });

    // A page can only be the homepage of a channel it's in. See "Homepage" in docs/spec/urls-and-seo.md
    it("should offer only the page's channels, and follow them", async () => {
        const api = fakeApi(settings({ channels: ['WEB'] }));
        await openBuilder(api);
        const homepageOf = within(pane().getByRole('group', { name: 'Homepage of' }));

        expect(homepageOf.getAllByRole('checkbox').map((checkbox) => (checkbox as HTMLInputElement).labels?.[0]?.textContent)).toEqual(['Web store']);

        fireEvent.click(within(pane().getByRole('group', { name: 'Channels' })).getByRole('checkbox', { name: 'Wholesale store' }));

        expect(homepageOf.getAllByRole('checkbox').map((checkbox) => (checkbox as HTMLInputElement).labels?.[0]?.textContent)).toEqual(['Web store', 'Wholesale store']);
        await saved();

        fireEvent.click(homepageOf.getByRole('checkbox', { name: 'Wholesale store' }));

        expect(api.saveSettings).toHaveBeenLastCalledWith({ homepageOf: ['B2B'] });
        await saved();
    });

    // One it has left stays its homepage, which the admin flags, until it's taken out here or the page is back in it
    it('should keep a channel the page has left among its homepages, saying so, until it is taken out', async () => {
        const api = fakeApi(settings({ channels: ['WEB'], homepageOf: ['B2B'] }));
        await openBuilder(api);
        const homepageOf = within(pane().getByRole('group', { name: 'Homepage of' }));
        const left = homepageOf.getByRole('checkbox', { name: "Wholesale store (the page isn't in this channel)" });

        expect(left).toBeChecked();

        fireEvent.click(left);

        expect(api.saveSettings).toHaveBeenLastCalledWith({ homepageOf: [] });
        await saved();
        expect(homepageOf.getAllByRole('checkbox').map((checkbox) => (checkbox as HTMLInputElement).labels?.[0]?.textContent)).toEqual(['Web store']);
    });

    it('should offer Homepage of only for a content type whose entries can be homepages', async () => {
        const api = fakeApi(settings(), { fetchRegistry: vi.fn(async () => ({ ...registry, contentTypes: [{ ...page, homepage: false }] })) });
        await openBuilder(api);

        expect(pane().queryByRole('group', { name: 'Homepage of' })).not.toBeInTheDocument();
        expect(pane().getByRole('group', { name: 'Channels' })).toBeInTheDocument();
    });

    it("should preview a page at its channel's root once the save makes it that channel's homepage", async () => {
        let homepageOf: string[] = [];
        let release = (): void => {};
        const api = fakeApi(settings({ channels: ['WEB', 'B2B'] }), {
            saveSettings: vi.fn(
                (changes: Partial<EntrySettings>) =>
                    new Promise<EntrySettings>((resolve) => {
                        release = () => {
                            homepageOf = changes.homepageOf ?? homepageOf;
                            resolve(settings({ channels: ['WEB', 'B2B'], homepageOf }));
                        };
                    }),
            ),
            createPreviewUrl: vi.fn(async ({ channel }: { channel: string }) =>
                homepageOf.includes(channel) ? `http://admin.example/en_US/?channel=${channel}` : `http://admin.example/en_US/about-us?channel=${channel}`,
            ),
        });
        await openBuilder(api);
        await waitFor(() => expect(screen.getByTitle('Preview')).toHaveAttribute('src', 'http://admin.example/en_US/about-us?channel=WEB'));
        const homepages = within(pane().getByRole('group', { name: 'Homepage of' }));

        // Another channel's homepage leaves the preview where it is
        fireEvent.click(homepages.getByRole('checkbox', { name: 'Wholesale store' }));
        release();
        await saved();
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);

        fireEvent.click(homepages.getByRole('checkbox', { name: 'Web store' }));
        // The preview asks where the page lives once the save is through
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);

        release();

        await waitFor(() => expect(screen.getByTitle('Preview')).toHaveAttribute('src', 'http://admin.example/en_US/?channel=WEB'));
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(2);
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
