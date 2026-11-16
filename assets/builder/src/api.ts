import type { Change } from './changeSet';
import type { BuilderConfig, ShownToAndDate } from './config';
import type { EntryResponse, EntrySettings, EntryStatus, EntryUrlsResponse, PresenceResponse, Registry, SaveResponse, StoredBlock, ValidationError } from './types';

/** The builder's JSON endpoints. They're internal, and change together with the app. */
export interface BuilderApi {
    fetchRegistry(): Promise<Registry>;
    fetchEntry(): Promise<EntryResponse>;
    /**
     * Saves the changes made since the last load or save, carrying the lock token from it. When colleagues saved since,
     * the answer carries the working copy with their changes, and the clashes.
     */
    saveChanges(changes: readonly Change[], lockToken: number): Promise<SaveResponse>;
    /** Says the editor still has the entry open, and answers with who else does and the working copy's lock token. */
    presence(): Promise<PresenceResponse>;
    /** Validates the working copy as the server holds it, as publish would, and answers with its errors. */
    validate(): Promise<ValidationError[]>;
    /**
     * Fails with an ApiError of status 422 and the reason when the server refuses: with the errors when validation
     * refuses, and with none when a listener vetoes.
     */
    publish(): Promise<EntryStatus>;
    /** Deletes the draft, and answers with the working copy that's left: the published content. */
    discard(): Promise<EntryResponse>;
    /**
     * Schedules a publish of the working copy at a time in UTC, and answers with the working copy, which it locks. Fails
     * with an ApiError of status 422 and the reason when the server refuses, as for a publish, or for a time that isn't
     * in the future.
     */
    schedule(at: string): Promise<EntryResponse>;
    /** Cancels the scheduled publish, and answers with the working copy, unlocked. */
    cancelSchedule(): Promise<EntryResponse>;
    /**
     * Saves the on-save settings that changed, outside the draft, and answers with all of them. Fails with an ApiError
     * of status 400 and the reason when the server refuses, such as a date window that ends before it starts.
     */
    saveSettings(changes: Partial<EntrySettings>): Promise<EntrySettings>;
    /** Asks for a preview token, and answers with the URL of the preview it turns on. */
    createPreviewUrl(preview: PreviewRequest): Promise<string>;
    /** Renders blocks of the working copy as the builder holds them, under a preview token, and answers their HTML by key. */
    renderPreview(token: string, blocks: readonly StoredBlock[]): Promise<Record<string, string>>;
}

/** What the preview shows the working copy in, to whom and when. The sample object and the placement come later. */
export interface PreviewRequest extends ShownToAndDate {
    channel: string;
    locale: string;
}

export class ApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
        /** The validation errors the server refused for, such as a publish's. */
        readonly errors: ValidationError[] = [],
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

type Fetch = (input: string, init?: RequestInit) => Promise<Response>;

interface Request {
    method?: 'GET' | 'PATCH' | 'POST' | 'PUT' | 'DELETE';
    headers?: Record<string, string>;
    body?: string;
}

const defaultFetch: Fetch = (input, init) => fetch(input, init);

/** Sends a request to one of the builder's endpoints, and answers with the JSON it answers 200 with. */
async function send(fetchFn: Fetch, url: string, { method = 'GET', headers = {}, body: requestBody }: Request = {}): Promise<unknown> {
    const response = await fetchFn(url, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...headers },
        ...(requestBody === undefined ? {} : { body: requestBody }),
    });

    const body: unknown = await response.json().catch(() => undefined);
    if (response.status !== 200) {
        throw new ApiError(errorMessage(body) ?? `${url} answered ${response.status}.`, response.status, (isObject(body) ? validationErrors(body.errors) : null) ?? []);
    }

    if (body === undefined) {
        throw new ApiError(`${url} didn't answer with JSON.`, response.status);
    }

    return body;
}

export function createApi(config: BuilderConfig, fetchFn: Fetch = defaultFetch): BuilderApi {
    const request = async (url: string, init: Request = {}): Promise<unknown> => send(fetchFn, url, init);

    const write = { 'X-CSRF-Token': config.csrfToken };

    return {
        async fetchRegistry() {
            return (await request(config.endpoints.registry)) as Registry;
        },

        async fetchEntry() {
            return (await request(config.endpoints.entry)) as EntryResponse;
        },

        async saveChanges(changes, lockToken) {
            const body = await request(config.endpoints.entry, {
                method: 'PATCH',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ lockToken, changes }),
            });

            entryStatus(body);
            if (!isObject(body) || typeof body.lockToken !== 'number') {
                throw new ApiError('The answer has no lock token.', 200);
            }

            return body as unknown as SaveResponse;
        },

        async presence() {
            const body = await request(config.endpoints.presence, { method: 'POST', headers: write });
            if (!isObject(body) || !Array.isArray(body.editors) || typeof body.lockToken !== 'number') {
                throw new ApiError('The answer has no editors.', 200);
            }

            return body as unknown as PresenceResponse;
        },

        async validate() {
            // It changes nothing, so it needs no CSRF token
            const body = await request(config.endpoints.validate, { method: 'POST' });
            const errors = isObject(body) ? validationErrors(body.errors) : null;
            if (errors === null) {
                throw new ApiError('The answer has no list of errors.', 200);
            }

            return errors;
        },

        async publish() {
            return entryStatus(await request(config.endpoints.publish, { method: 'POST', headers: write }));
        },

        async discard() {
            return (await request(config.endpoints.discard, { method: 'POST', headers: write })) as EntryResponse;
        },

        async schedule(at) {
            return (await request(config.endpoints.schedule, {
                method: 'POST',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ at }),
            })) as EntryResponse;
        },

        async cancelSchedule() {
            return (await request(config.endpoints.schedule, { method: 'DELETE', headers: write })) as EntryResponse;
        },

        async saveSettings(changes) {
            const body = await request(config.endpoints.settings, {
                method: 'PUT',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify(changes),
            });

            const settings = isObject(body) ? body.settings : undefined;
            if (!isObject(settings)) {
                throw new ApiError('The answer has no settings.', 200);
            }

            return settings as unknown as EntrySettings;
        },

        async createPreviewUrl({ channel, locale, shownTo, date }) {
            const body = await request(config.endpoints.previewToken, {
                method: 'POST',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ entry: config.entryUuid, channel, locale, shownTo, date }),
            });

            const url = isObject(body) ? body.url : undefined;
            if (typeof url !== 'string') {
                throw new ApiError('The answer has no preview URL.', 200);
            }

            return url;
        },

        async renderPreview(token, blocks) {
            const body = await request(`${config.endpoints.previewRender}?_cms_preview=${encodeURIComponent(token)}`, {
                method: 'POST',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ blocks }),
            });

            const html = isObject(body) ? body.blocks : undefined;
            if (!isObject(html) || !Object.values(html).every((value) => typeof value === 'string')) {
                throw new ApiError('The answer has no rendered blocks.', 200);
            }

            return html as Record<string, string>;
        },
    };
}

/** The endpoints of an entry's URLs, which the URL & SEO tab uses. They read and change nothing on the server. */
export interface UrlsApi {
    /** The slug Sylius's slug generator makes of a title. */
    generateSlug(title: string): Promise<string>;
    /** The warnings about the entry's URLs and the URLs of its old slugs, as the server has them. */
    fetchUrls(): Promise<EntryUrlsResponse>;
}

/** The URLs endpoints the builder page gives, or null when it gives neither. */
export function createUrlsApi(config: BuilderConfig, fetchFn: Fetch = defaultFetch): UrlsApi | null {
    const { slug, urls } = config.endpoints;
    if (slug === undefined || urls === undefined) {
        return null;
    }

    return {
        async generateSlug(title) {
            const body = await send(fetchFn, `${slug}?title=${encodeURIComponent(title)}`);
            const generated = isObject(body) ? body.slug : undefined;
            if (typeof generated !== 'string') {
                throw new ApiError('The answer has no slug.', 200);
            }

            return generated;
        },

        async fetchUrls() {
            const body = await send(fetchFn, urls);
            const warnings = isObject(body) ? validationErrors(body.warnings) : null;
            const oldUrls = isObject(body) ? body.oldUrls : undefined;
            if (warnings === null || !isObject(oldUrls) || Array.isArray(oldUrls) || !Object.values(oldUrls).every((list) => Array.isArray(list) && list.every((url) => typeof url === 'string'))) {
                throw new ApiError('The answer has no URL warnings and old URLs.', 200);
            }

            return { warnings, oldUrls: oldUrls as Record<string, string[]> };
        },
    };
}

const statuses: readonly unknown[] = ['never_published', 'published', 'unpublished_changes'] satisfies EntryStatus[];

function entryStatus(body: unknown): EntryStatus {
    const status = isObject(body) ? body.status : undefined;
    if (!statuses.includes(status)) {
        throw new ApiError('The answer has no entry status.', 200);
    }

    return status as EntryStatus;
}

/** The validation errors in a list from the server, or null when it isn't one. */
function validationErrors(list: unknown): ValidationError[] | null {
    const isNullableString = (value: unknown): boolean => value === null || typeof value === 'string';
    const isError = (error: unknown): boolean =>
        isObject(error) && isNullableString(error.block) && isNullableString(error.field) && isNullableString(error.locale) && typeof error.message === 'string';

    return Array.isArray(list) && list.every(isError) ? (list as ValidationError[]) : null;
}

function errorMessage(body: unknown): string | undefined {
    return isObject(body) && typeof body.error === 'string' ? body.error : undefined;
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
