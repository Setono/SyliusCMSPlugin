import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, PreviewRequest } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { Entry, EntryResponse, EntryStatus, PresenceResponse, SaveResponse, StoredBlock, ValidationError } from '../src/types';
import { block, entry, registry, settings, strings } from './fixtures';

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
        { code: 'WEB', name: 'Web store', locales: ['en_US', 'da_DK'] },
        { code: 'CH', name: 'Swiss store', locales: ['de_CH'] },
    ],
    locales: [
        { code: 'en_US', name: 'English (United States)', fallbackChain: ['en_US'] },
        { code: 'da_DK', name: 'Danish (Denmark)', fallbackChain: ['da_DK', 'en_US'] },
        { code: 'de_CH', name: 'German (Switzerland)', fallbackChain: ['de_CH', 'de_DE', 'en_US'] },
    ],
    customerGroups: [],
};

const translationStrings: Record<string, string> = {
    ...strings,
    'setono_sylius_cms.builder.locale': 'Language',
    'setono_sylius_cms.builder.all_languages': 'All languages',
    'setono_sylius_cms.builder.all_languages_help': 'A change here changes every language',
    'setono_sylius_cms.builder.falls_back_to': 'Empty here, so it shows the %locale% text',
    'setono_sylius_cms.builder.copy_it_here': 'Copy it here',
    'setono_sylius_cms.builder.no_translation': 'This entry has no translation in %locale% yet.',
    'setono_sylius_cms.builder.add_translation': 'Add %locale% translation',
    'setono_sylius_cms.builder.show_in_locale': 'Show in %locale%',
    'setono_sylius_cms.builder.show_in_locale_help': "Off holds the translation back, so shoppers don't see the entry in %locale%.",
    'setono_sylius_cms.builder.entry_title': 'Title',
};

/** An entry with its English translation, and a Danish one when danish is given. */
function entryWith(blocks: StoredBlock[], danish: { title: string; shown: boolean } | null = null): Entry {
    const base = entry(blocks);
    if (danish === null) {
        return base;
    }

    return { ...base, translations: { ...base.translations, da_DK: { shown: danish.shown, fields: { slug: 'om-os', title: danish.title }, translated: {} } } };
}

function fakeApi(edited: Entry, status: EntryStatus = 'never_published') {
    return {
        fetchRegistry: vi.fn(async () => ({ ...registry, strings: translationStrings })),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: edited, settings: settings(), status, lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status, lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: edited, settings: settings(), status, lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async ({ locale }: PreviewRequest) => `http://admin.example/${locale}/about-us?_cms_preview=abc`),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
    } satisfies BuilderApi;
}

async function openBuilder(api: BuilderApi) {
    render(<App config={config} api={api} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
}

function switchTo(locale: string) {
    fireEvent.change(screen.getByRole('combobox', { name: 'Language' }), { target: { value: locale } });
}

function selectHeading() {
    fireEvent.click(within(screen.getByRole('navigation', { name: 'Blocks' })).getAllByRole('button', { name: /Heading/ })[0] as HTMLElement);
}

describe('the locale switcher', () => {
    it("should offer the locales of the entry's channels and reload the preview in the one picked", async () => {
        const api = fakeApi(entryWith([block('a', { text: { en_US: 'Welcome' } })], { title: 'Om os', shown: true }));
        await openBuilder(api);

        const switcher = screen.getByRole('combobox', { name: 'Language' });
        expect(within(switcher).getAllByRole('option').map((option) => option.textContent)).toEqual(['English (United States)', 'Danish (Denmark)']);
        expect(switcher).toHaveValue('en_US');
        await waitFor(() => expect(screen.getByTitle('Preview')).toHaveAttribute('src', 'http://admin.example/en_US/about-us?_cms_preview=abc'));

        switchTo('da_DK');

        expect(switcher).toHaveValue('da_DK');
        await waitFor(() => expect(screen.getByTitle('Preview')).toHaveAttribute('src', 'http://admin.example/da_DK/about-us?_cms_preview=abc'));
        expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'da_DK' });
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Om os');
    });
});

describe('a locale the entry has no translation in', () => {
    it('should show a banner instead of the preview, with a way to add a translation from the one it falls back to', async () => {
        const api = fakeApi(entryWith([block('a', { text: { en_US: 'Welcome' } })]));
        await openBuilder(api);
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenCalledTimes(1));

        switchTo('da_DK');

        expect(screen.getByText('This entry has no translation in Danish (Denmark) yet.')).toBeInTheDocument();
        expect(screen.queryByTitle('Preview')).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('About us');

        fireEvent.click(screen.getByRole('button', { name: 'Add Danish (Denmark) translation' }));

        expect(screen.queryByText('This entry has no translation in Danish (Denmark) yet.')).not.toBeInTheDocument();
        await waitFor(() =>
            expect(api.saveChanges).toHaveBeenCalledWith(
                [
                    { op: 'set_shown', locale: 'da_DK', shown: true },
                    { op: 'set_entry_value', field: 'slug', locale: 'da_DK', value: 'about-us' },
                    { op: 'set_entry_value', field: 'title', locale: 'da_DK', value: 'About us' },
                ],
                0,
            ),
        );
        await waitFor(() => expect(api.createPreviewUrl).toHaveBeenLastCalledWith({ channel: 'WEB', locale: 'da_DK' }));
        expect(screen.getByLabelText('Title')).toHaveValue('About us');
    });

    it('should hold back a translation added after the first publish', async () => {
        const api = fakeApi(entryWith([block('a')]), 'published');
        await openBuilder(api);
        switchTo('da_DK');

        fireEvent.click(screen.getByRole('button', { name: 'Add Danish (Denmark) translation' }));

        expect(screen.getByRole('checkbox', { name: 'Show in Danish (Denmark)' })).not.toBeChecked();
    });
});

describe('the translation in the edited locale', () => {
    it('should switch it shown or held back', async () => {
        const api = fakeApi(entryWith([block('a')], { title: 'Om os', shown: false }), 'published');
        await openBuilder(api);
        switchTo('da_DK');

        const shown = screen.getByRole('checkbox', { name: 'Show in Danish (Denmark)' });
        expect(shown).not.toBeChecked();
        fireEvent.click(shown);

        expect(shown).toBeChecked();
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_shown', locale: 'da_DK', shown: true }], 0));
    });

    it('should edit its title, which shows the title it falls back to while it is empty', async () => {
        const api = fakeApi(entryWith([block('a')], { title: 'Om os', shown: true }));
        await openBuilder(api);
        switchTo('da_DK');

        const title = screen.getByLabelText('Title');
        expect(title).toHaveValue('Om os');
        fireEvent.change(title, { target: { value: '' } });

        expect(title).toHaveValue('');
        expect(title).toHaveAttribute('placeholder', 'About us');
        expect(screen.getByText('Empty here, so it shows the English (United States) text')).toBeInTheDocument();
    });
});

describe('the fields of a block', () => {
    it('should mark a shared field with an All languages badge, and leave a translatable one without', async () => {
        await openBuilder(fakeApi(entryWith([block('a', { text: { en_US: 'Welcome' } })])));
        selectHeading();

        const badge = screen.getByText('All languages');
        expect(badge).toHaveAttribute('title', 'A change here changes every language');
        expect(badge.closest('div')).toHaveTextContent('Level');
        expect(screen.getAllByText('All languages')).toHaveLength(1);
    });

    it('should show the value an empty translatable field falls back to as a placeholder, and copy it here', async () => {
        const api = fakeApi(entryWith([block('a', { text: { en_US: 'Welcome' } })], { title: 'Om os', shown: true }));
        await openBuilder(api);
        switchTo('da_DK');
        selectHeading();

        const text = screen.getByLabelText('Text');
        expect(text).toHaveValue('');
        expect(text).toHaveAttribute('placeholder', 'Welcome');
        expect(text).toHaveAccessibleDescription('Empty here, so it shows the English (United States) text Copy it here');

        fireEvent.click(screen.getByRole('button', { name: 'Copy it here' }));

        expect(text).toHaveValue('Welcome');
        expect(screen.queryByRole('button', { name: 'Copy it here' })).not.toBeInTheDocument();
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_value', key: 'a', field: 'text', locale: 'da_DK', value: 'Welcome' }], 0));

        fireEvent.change(text, { target: { value: 'Velkommen' } });
        switchTo('en_US');

        expect(screen.getByLabelText('Text')).toHaveValue('Welcome');
    });

    it('should show no placeholder for a field empty in every locale it falls back through', async () => {
        await openBuilder(fakeApi(entryWith([block('a', { text: { da_DK: 'Velkommen' } })], { title: 'Om os', shown: true })));
        selectHeading();

        expect(screen.getByLabelText('Text')).not.toHaveAttribute('placeholder');
        expect(screen.queryByRole('button', { name: 'Copy it here' })).not.toBeInTheDocument();
    });
});
