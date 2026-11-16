import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ApiError, type BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import { formatDateTime, fromLocalInput } from '../src/dates';
import type { EntryResponse, EntryStatus, PresenceResponse, Schedule, SaveResponse, ValidationError } from '../src/types';
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
    customerGroups: [],
};

const AT = '2026-11-27T08:00:00Z';

const waiting: Schedule = { at: AT, by: 'Jane Roe', status: 'publishes_at_a_time', reason: null };

/** The working copy with a heading, and the entry's scheduled publish. */
function answer(schedule: Schedule | null = null): EntryResponse {
    return { entry: entry([block('a', { text: { en_US: 'Welcome' } })]), settings: settings(), status: 'unpublished_changes', lockToken: 0, schedule, editors: [] };
}

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => registry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => answer()),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'unpublished_changes', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => answer()),
        schedule: vi.fn(async (): Promise<EntryResponse> => answer(waiting)),
        cancelSchedule: vi.fn(async (): Promise<EntryResponse> => answer()),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us?_cms_preview=abc.def'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

async function openBuilder(api: BuilderApi = fakeApi()) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function topBar(): HTMLElement {
    return screen.getByRole('banner');
}

function tree(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Blocks' });
}

function selectionPane(): HTMLElement {
    return screen.getByRole('complementary');
}

/** What the builder looks like read-only: Cancel the schedule in place of Publish, and nothing that edits. */
function expectReadOnly(): void {
    expect(within(topBar()).getByRole('button', { name: 'Cancel the schedule' })).toBeInTheDocument();
    expect(within(topBar()).queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument();
    expect(within(topBar()).queryByRole('button', { name: 'Discard changes' })).not.toBeInTheDocument();
    expect(within(tree()).queryByRole('button', { name: 'Add block' })).not.toBeInTheDocument();
    expect(within(tree()).queryByRole('button', { name: 'Delete block' })).not.toBeInTheDocument();
}

describe('scheduled publishes in the builder', () => {
    it('should open read-only while a scheduled publish waits, saying when it publishes', async () => {
        const api = fakeApi({ fetchEntry: vi.fn(async () => answer(waiting)) });
        await openBuilder(api);

        expectReadOnly();
        const at = formatDateTime(AT);
        expect(within(topBar()).getByText(`Publishes ${at}`)).toBeInTheDocument();
        expect(screen.getByText(`The draft is locked: it publishes ${at}. Cancel the schedule to edit it.`)).toBeInTheDocument();

        // The block is only looked at
        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Welcome' }));
        const text = within(selectionPane()).getByRole('textbox', { name: 'Text' });
        expect(text).toBeDisabled();
        expect(within(selectionPane()).getByRole('button', { name: 'Delete block' })).toBeDisabled();
        fireEvent.change(text, { target: { value: 'Hello' } });
        expect(within(tree()).getByRole('button', { name: 'Heading Welcome' })).toBeInTheDocument();

        // The drafted title is locked, the on-save settings aren't
        fireEvent.click(within(tree()).getByRole('button', { name: 'Entry settings' }));
        expect(within(selectionPane()).getByRole('textbox', { name: 'setono_sylius_cms.builder.entry_title' })).toBeDisabled();
        expect(within(selectionPane()).getByRole('checkbox', { name: 'Enabled' })).toBeEnabled();

        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(api.saveChanges).not.toHaveBeenCalled();
    });

    it('should say an overdue scheduled publish may be waiting for a worker that is not running', async () => {
        await openBuilder(fakeApi({ fetchEntry: vi.fn(async () => answer({ ...waiting, status: 'scheduled_publish_overdue' })) }));

        expectReadOnly();
        expect(within(topBar()).getByText('Scheduled publish overdue')).toBeInTheDocument();
        expect(screen.getByText(`The draft is locked: its publish was scheduled for ${formatDateTime(AT)} and hasn't run. The Messenger worker may not be running.`)).toBeInTheDocument();
    });

    it('should schedule the publish from the Publish button’s menu, and then be read-only', async () => {
        const api = fakeApi();
        await openBuilder(api);

        fireEvent.click(within(topBar()).getByRole('button', { name: 'More ways to publish' }));
        const dialog = await screen.findByRole('dialog', { name: 'Schedule the publish' });
        const submit = within(dialog).getByRole('button', { name: 'Schedule the publish' });
        expect(submit).toBeDisabled();

        fireEvent.change(within(dialog).getByLabelText('Publish at'), { target: { value: '2026-11-27T09:00' } });
        fireEvent.click(submit);

        await waitFor(() => expect(api.schedule).toHaveBeenCalledWith(fromLocalInput('2026-11-27T09:00')));
        await within(topBar()).findByRole('button', { name: 'Cancel the schedule' });
        expectReadOnly();
        expect(screen.queryByRole('dialog', { name: 'Schedule the publish' })).not.toBeInTheDocument();
    });

    it('should say why the server refused to schedule, and keep the menu open', async () => {
        const api = fakeApi({ schedule: vi.fn(() => Promise.reject(new ApiError('Pick a time in the future.', 422))) });
        await openBuilder(api);

        fireEvent.click(within(topBar()).getByRole('button', { name: 'More ways to publish' }));
        const dialog = await screen.findByRole('dialog', { name: 'Schedule the publish' });
        fireEvent.change(within(dialog).getByLabelText('Publish at'), { target: { value: '2020-01-01T09:00' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Schedule the publish' }));

        expect(await within(dialog).findByRole('alert')).toHaveTextContent("Couldn't schedule: Pick a time in the future.");
        expect(within(topBar()).getByRole('button', { name: 'Publish' })).toBeInTheDocument();
    });

    it('should cancel the schedule, and be editable again', async () => {
        const api = fakeApi({ fetchEntry: vi.fn(async () => answer(waiting)) });
        await openBuilder(api);

        fireEvent.click(within(topBar()).getByRole('button', { name: 'Cancel the schedule' }));

        await within(topBar()).findByRole('button', { name: 'Publish' });
        expect(api.cancelSchedule).toHaveBeenCalledTimes(1);
        expect(within(tree()).getByRole('button', { name: 'Add block' })).toBeInTheDocument();
        expect(screen.queryByText(/The draft is locked/)).not.toBeInTheDocument();

        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Welcome' }));
        fireEvent.change(within(selectionPane()).getByRole('textbox', { name: 'Text' }), { target: { value: 'Hello' } });
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledTimes(1));
    });

    it('should load the working copy again, read-only, when a colleague’s scheduled publish refuses a save', async () => {
        const fetchEntry = vi.fn<() => Promise<EntryResponse>>().mockResolvedValueOnce(answer()).mockResolvedValue(answer(waiting));
        const api = fakeApi({
            fetchEntry,
            saveChanges: vi.fn(() => Promise.reject(new ApiError('A publish scheduled for 2026-11-27T08:00:00+00:00 locks the draft', 423))),
        });
        await openBuilder(api);

        fireEvent.click(within(tree()).getByRole('button', { name: 'Heading Welcome' }));
        fireEvent.change(within(selectionPane()).getByRole('textbox', { name: 'Text' }), { target: { value: 'Hello' } });

        await within(topBar()).findByRole('button', { name: 'Cancel the schedule' });
        expect(fetchEntry).toHaveBeenCalledTimes(2);
        expectReadOnly();
        // What couldn't be saved goes with the working copy it was made on
        expect(within(tree()).getByRole('button', { name: 'Heading Welcome' })).toBeInTheDocument();
    });

    it('should say why a scheduled publish was stopped when it ran, and stay editable', async () => {
        await openBuilder(fakeApi({ fetchEntry: vi.fn(async () => answer({ ...waiting, status: 'scheduled_publish_failed', reason: 'The campaign was postponed' })) }));

        expect(within(topBar()).getByText('Scheduled publish failed')).toBeInTheDocument();
        expect(screen.getByText(`The publish scheduled for ${formatDateTime(AT)} was stopped when it ran: The campaign was postponed`)).toBeInTheDocument();
        expect(within(topBar()).getByRole('button', { name: 'Publish' })).toBeInTheDocument();
        expect(within(topBar()).getByRole('button', { name: 'More ways to publish' })).toBeInTheDocument();
        expect(within(tree()).getByRole('button', { name: 'Add block' })).toBeInTheDocument();
    });
});
