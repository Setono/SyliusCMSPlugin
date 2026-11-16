import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { Entry, EntryResponse, EntryStatus, PresenceResponse, Registry, SaveResponse, StoredLayout, ValidationError } from '../src/types';
import { block, entry, page, registry, settings, standard } from './fixtures';

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
        settings: '/settings',
        previewToken: '/preview/token',
        previewRender: '/preview/render',
        presence: '/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

function inLayout(layout: StoredLayout): Entry {
    const base = entry([block('a', { text: { en_US: 'Welcome' } })]);

    return { ...base, tree: { ...base.tree, layout } };
}

/** The registry, with the entry title's label */
const titled: Registry = { ...registry, strings: { ...registry.strings, 'setono_sylius_cms.builder.entry_title': 'Title' } };

function fakeApi(edited: Entry = inLayout({ code: 'standard', version: 1, fields: {}, translated: {} }), withRegistry: Registry = titled) {
    let previews = 0;

    return {
        fetchRegistry: vi.fn(async () => withRegistry),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: edited, settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'unpublished_changes', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: edited, settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => `http://admin.example/en_US/about-us?_cms_preview=token-${++previews}`),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    } satisfies BuilderApi;
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function settingsPanel() {
    return within(screen.getByRole('tabpanel', { name: 'Settings' }));
}

describe('the layout in the entry settings', () => {
    it("should offer each layout as a card, with the entry's own picked, and the fields of that layout", async () => {
        await openBuilder(fakeApi());

        const layouts = within(settingsPanel().getByRole('group', { name: 'Layout' }));
        expect(layouts.getByRole('radio', { name: 'Standard' })).toBeChecked();
        expect(layouts.getByRole('radio', { name: 'Landing' })).not.toBeChecked();
        // A layout without a thumbnail has a plain card
        expect([...screen.getByRole('group', { name: 'Layout' }).querySelectorAll('img')].map((image) => image.getAttribute('src'))).toEqual(['/thumbnail/layout/standard.svg']);
        expect(settingsPanel().getByRole('checkbox', { name: 'Show title' })).toBeChecked();
        expect(settingsPanel().getByRole('textbox', { name: 'Title' })).not.toHaveAccessibleDescription('Not shown in this layout');
    });

    it('should switch layout, show the new layout fields, mark the title not shown, save the switch and reload the preview', async () => {
        const api = fakeApi();
        await openBuilder(api);
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(1));

        fireEvent.click(settingsPanel().getByRole('radio', { name: 'Landing' }));

        expect(settingsPanel().getByRole('radio', { name: 'Landing' })).toBeChecked();
        expect(settingsPanel().queryByRole('checkbox', { name: 'Show title' })).not.toBeInTheDocument();
        expect(settingsPanel().getByRole('checkbox', { name: 'Transparent header' })).not.toBeChecked();
        expect(settingsPanel().getByRole('textbox', { name: 'Title' })).toHaveAccessibleDescription('Not shown in this layout');
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_layout', layout: { code: 'landing', version: 2, fields: {}, translated: {} } }], 0));
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(2));
    });

    it('should save a layout field and reload the preview, which a title the layout does not show does not', async () => {
        const api = fakeApi(inLayout({ code: 'landing', version: 2, fields: {}, translated: {} }));
        await openBuilder(api);
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(1));

        fireEvent.change(settingsPanel().getByRole('textbox', { name: 'Title' }), { target: { value: 'About' } });
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledTimes(1));
        expect(api.createPreviewUrl).toHaveBeenCalledTimes(1);

        fireEvent.change(settingsPanel().getByRole('textbox', { name: 'Tagline' }), { target: { value: 'Big news' } });

        await waitFor(() => expect(api.saveChanges).toHaveBeenLastCalledWith([{ op: 'set_layout_value', layout: 'landing', field: 'tagline', locale: 'en_US', value: 'Big news' }], 1));
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(2));
    });

    it('should flag a layout that was removed from code, show none of its fields, and clear the flag once one is picked', async () => {
        await openBuilder(fakeApi(inLayout({ code: 'gone', version: 1, fields: { tagline: { en_US: 'Big news' } }, translated: {} })));

        expect(settingsPanel().getByRole('status')).toHaveTextContent('Its layout "gone" was removed from the code or disabled, so it shows in the default layout, Standard, until you pick one.');
        expect(settingsPanel().getByRole('radio', { name: 'Standard' })).not.toBeChecked();
        expect(settingsPanel().queryByRole('checkbox', { name: 'Show title' })).not.toBeInTheDocument();

        fireEvent.click(settingsPanel().getByRole('radio', { name: 'Standard' }));

        expect(settingsPanel().queryByText(/was removed from the code/)).not.toBeInTheDocument();
        expect(settingsPanel().getByRole('radio', { name: 'Standard' })).toBeChecked();
        expect(settingsPanel().getByRole('checkbox', { name: 'Show title' })).toBeChecked();
    });

    it('should hide the picker of a content type with one layout to pick, and still show its fields', async () => {
        const disabled = { ...page.layouts[1]!, disabled: true };
        await openBuilder(fakeApi(undefined, { ...titled, contentTypes: [{ ...page, layouts: [standard, disabled] }] }));

        expect(settingsPanel().queryByRole('group', { name: 'Layout' })).not.toBeInTheDocument();
        expect(settingsPanel().getByRole('checkbox', { name: 'Show title' })).toBeChecked();
    });

    it('should flag a disabled layout, and offer only the others', async () => {
        const disabled = { ...page.layouts[1]!, disabled: true };
        await openBuilder(fakeApi(inLayout({ code: 'landing', version: 2, fields: {}, translated: {} }), { ...titled, contentTypes: [{ ...page, layouts: [standard, disabled] }] }));

        expect(settingsPanel().getByRole('status')).toHaveTextContent('Its layout "landing" was removed');
        expect(settingsPanel().getAllByRole('radio').map((radio) => radio.getAttribute('value'))).toEqual(['standard']);
    });
});
