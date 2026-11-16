import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { BuilderApi, UrlsApi } from '../src/api';
import { App } from '../src/App';
import type { BuilderConfig } from '../src/config';
import type { EntryResponse, EntryStatus, EntryUrlsResponse, PresenceResponse, Registry, SaveResponse, ValidationError } from '../src/types';
import { entry, page, registry, settings, strings } from './fixtures';

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

/** Pages live at the root of the shop, and a project's stores under stores. */
const withUrls: Registry = {
    ...registry,
    contentTypes: [{ ...page, prefix: '' }],
    strings: { ...strings, 'setono_sylius_cms.builder.entry_title': 'Title' },
};

function fakeApi(overrides: Partial<BuilderApi> = {}) {
    return {
        fetchRegistry: vi.fn(async () => withUrls),
        fetchEntry: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveChanges: vi.fn(async (): Promise<SaveResponse> => ({ status: 'unpublished_changes', lockToken: 1 })),
        validate: vi.fn(async (): Promise<ValidationError[]> => []),
        publish: vi.fn(async (): Promise<EntryStatus> => 'published'),
        discard: vi.fn(async (): Promise<EntryResponse> => ({ entry: entry(), settings: settings(), status: 'published', lockToken: 0, editors: [] })),
        saveSettings: vi.fn(async () => settings()),
        createPreviewUrl: vi.fn(async () => 'http://admin.example/en_US/about-us'),
        renderPreview: vi.fn(async (): Promise<Record<string, string>> => ({})),
        schedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        cancelSchedule: vi.fn(() => Promise.reject(new Error('Not scheduled in this test'))),
        presence: vi.fn(async (): Promise<PresenceResponse> => ({ editors: [], lockToken: 0 })),
        ...overrides,
    };
}

function fakeUrlsApi(urls: EntryUrlsResponse = { warnings: [], oldUrls: {} }) {
    return {
        generateSlug: vi.fn(async (title: string) => title.toLowerCase().replace(/[^a-z0-9]+/g, '-')),
        fetchUrls: vi.fn(async () => urls),
    } satisfies UrlsApi;
}

async function openUrlTab(api: BuilderApi = fakeApi(), urlsApi: UrlsApi | null = fakeUrlsApi()) {
    render(<App config={config} api={api} urlsApi={urlsApi} autosaveDelay={10} />);
    await screen.findByRole('heading', { level: 1 });
    fireEvent.click(pane().getByRole('tab', { name: 'URL & SEO' }));

    return within(pane().getByRole('tabpanel', { name: 'URL & SEO' }));
}

function pane() {
    return within(screen.getByRole('complementary'));
}

function slugError(locale: string, message: string): ValidationError {
    return { block: null, field: 'slug', locale, message };
}

describe('the URL & SEO tab', () => {
    it('should sit between Settings and More for a content type with its own URL', async () => {
        await openUrlTab();

        expect(pane().getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Settings', 'URL & SEO', 'More']);
    });

    it('should not be there for a content type without a URL of its own', async () => {
        render(<App config={config} api={fakeApi({ fetchRegistry: vi.fn(async () => ({ ...withUrls, contentTypes: [{ ...page, prefix: null }] })) })} urlsApi={fakeUrlsApi()} />);
        await screen.findByRole('heading', { level: 1 });

        expect(pane().getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Settings', 'More']);
    });

    it('should show the slug of the edited locale with the URL it gives, and save a slug typed', async () => {
        const api = fakeApi();
        const panel = await openUrlTab(api);
        const slug = panel.getByLabelText('Slug');

        expect(slug).toHaveValue('about-us');
        expect(slug).toHaveAccessibleDescription('Generated from the title until you change it. The URL: /en_US/about-us');

        fireEvent.change(slug, { target: { value: 'about/team' } });

        expect(slug).toHaveAccessibleDescription('Generated from the title until you change it. The URL: /en_US/about/team');
        await waitFor(() => expect(api.saveChanges).toHaveBeenCalledWith([{ op: 'set_entry_value', field: 'slug', locale: 'en_US', value: 'about/team' }], 0));
    });

    it('should put the prefix of the content type in the URL', async () => {
        const api = fakeApi({ fetchRegistry: vi.fn(async () => ({ ...withUrls, contentTypes: [{ ...page, prefix: 'pg' }] })) });
        const panel = await openUrlTab(api);

        expect(panel.getByLabelText('Slug')).toHaveAccessibleDescription('Generated from the title until you change it. The URL: /en_US/pg/about-us');
    });

    it('should show the errors publish would refuse on the slug, and leave them out of the errors above the tabs while it is open', async () => {
        const api = fakeApi({ validate: vi.fn(async () => [slugError('en_US', 'The URL /en_US/about-us is already the URL of "About" in Web store.')]) });
        const panel = await openUrlTab(api);

        await waitFor(() => expect(panel.getByLabelText('Slug')).toHaveAccessibleDescription(/is already the URL of "About" in Web store\.$/));
        expect(pane().getAllByText('The URL /en_US/about-us is already the URL of "About" in Web store.')).toHaveLength(1);

        fireEvent.click(pane().getByRole('tab', { name: 'Settings' }));
        expect(pane().getByText('slug: The URL /en_US/about-us is already the URL of "About" in Web store.')).toBeInTheDocument();
    });

    it('should show why the server kept the last saved slug until a slug it saves', async () => {
        const saveChanges = vi.fn(async (): Promise<SaveResponse> => ({ status: 'unpublished_changes', lockToken: 1 }));
        saveChanges.mockResolvedValueOnce({ status: 'unpublished_changes', lockToken: 0, refused: [slugError('en_US', 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.')] });
        const panel = await openUrlTab(fakeApi({ saveChanges }));
        const slug = panel.getByLabelText('Slug');

        fireEvent.change(slug, { target: { value: 'cart' } });
        await waitFor(() => expect(slug).toHaveAccessibleDescription(/The URL \/en_US\/cart is taken by the route sylius_shop_cart_summary\.$/));
        expect(slug).toHaveValue('cart');

        // Another change leaves the refusal standing, and another slug that saves clears it
        fireEvent.click(pane().getByRole('tab', { name: 'Settings' }));
        fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Cart' } });
        await waitFor(() => expect(saveChanges).toHaveBeenCalledTimes(2));
        fireEvent.click(pane().getByRole('tab', { name: 'URL & SEO' }));
        expect(screen.getByLabelText('Slug')).toHaveAccessibleDescription(/taken by the route sylius_shop_cart_summary\.$/);

        fireEvent.change(screen.getByLabelText('Slug'), { target: { value: 'basket' } });
        await waitFor(() => expect(saveChanges).toHaveBeenCalledTimes(3));
        await waitFor(() => expect(screen.getByLabelText('Slug')).toHaveAccessibleDescription('Generated from the title until you change it. The URL: /en_US/basket'));
    });

    it('should show the warnings of the edited locale and the old URLs that redirect', async () => {
        const urlsApi = fakeUrlsApi({
            warnings: [slugError('en_US', '"Contact" has the URL /en_US/about-us in its draft too.'), slugError('da_DK', 'Not this one')],
            oldUrls: { en_US: ['/en_US/about', '/en_US/about-our-shop'], da_DK: ['/da_DK/om'] },
        });
        const panel = await openUrlTab(fakeApi(), urlsApi);

        expect(await panel.findByText('"Contact" has the URL /en_US/about-us in its draft too.')).toBeInTheDocument();
        expect(panel.queryByText('Not this one')).not.toBeInTheDocument();
        expect(within(panel.getByRole('list', { name: 'Redirects from' })).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
            '/en_US/about',
            '/en_US/about-our-shop',
        ]);
    });

    it('should read the warnings and the old URLs again after a save and a publish', async () => {
        const urlsApi = fakeUrlsApi();
        const api = fakeApi();
        const panel = await openUrlTab(api, urlsApi);
        await waitFor(() => expect(urlsApi.fetchUrls).toHaveBeenCalledTimes(1));

        fireEvent.change(panel.getByLabelText('Slug'), { target: { value: 'about-our-shop' } });
        await waitFor(() => expect(urlsApi.fetchUrls.mock.calls.length).toBeGreaterThan(1));

        urlsApi.fetchUrls.mockResolvedValue({ warnings: [], oldUrls: { en_US: ['/en_US/about-us'] } });
        fireEvent.click(screen.getByRole('button', { name: 'Publish' }));

        expect(await within(await panel.findByRole('list', { name: 'Redirects from' })).findByText('/en_US/about-us')).toBeInTheDocument();
    });

    it('should let the slug follow the title until the editor changes it', async () => {
        const urlsApi = fakeUrlsApi();
        await openUrlTab(fakeApi(), urlsApi);
        fireEvent.click(pane().getByRole('tab', { name: 'Settings' }));

        fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'About our shop' } });
        fireEvent.click(pane().getByRole('tab', { name: 'URL & SEO' }));

        await waitFor(() => expect(screen.getByLabelText('Slug')).toHaveValue('about-our-shop'));
        expect(urlsApi.generateSlug).toHaveBeenCalledWith('About us');
        expect(urlsApi.generateSlug).toHaveBeenCalledWith('About our shop');
    });

    it('should edit the slug without following the title when the builder page gives no slug endpoint', async () => {
        await openUrlTab(fakeApi(), null);
        fireEvent.click(pane().getByRole('tab', { name: 'Settings' }));

        fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'About our shop' } });
        fireEvent.click(pane().getByRole('tab', { name: 'URL & SEO' }));
        await new Promise((resolve) => setTimeout(resolve, 400));

        expect(screen.getByLabelText('Slug')).toHaveValue('about-us');
    });
});
