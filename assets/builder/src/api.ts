import type { Change } from './changeSet';
import { withUuid, type BuilderConfig, type ShownToAndDate } from './config';
import type { EntryResponse, EntrySettings, EntryStatus, EntryUrlsResponse, InsertedPreset, PresenceResponse, PresetDetails, PresetIndex, PresetListing, Registry, SavedPreset, SaveResponse, StoredBlock, ValidationError } from './types';

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
    /** The blocks a snippet shows, from its published content, which Detach to edit here copies. Absent without its endpoint. */
    snippetBlocks?(snippet: string): Promise<StoredBlock[]>;
    /**
     * The block presets at a spot in the working copy as the server has it, the top level or a block's children, and the
     * categories in use. Absent without its endpoint.
     */
    presets?(parent: string | null): Promise<PresetIndex>;
    /** A preset's blocks ready to insert into the entry. Fails with an ApiError of status 422 and why when it refuses. Absent without its endpoint. */
    insertPreset?(id: string): Promise<InsertedPreset>;
    /**
     * Saves blocks of the working copy as the server has it, by their keys, as a new editor preset. Fails with an ApiError
     * of status 400 and why when the server refuses, such as for no name. Absent without the editor presets' endpoints.
     */
    createPreset?(blocks: readonly string[], details: PresetDetails): Promise<SavedPreset>;
    /** Replaces an editor preset's blocks with blocks of the working copy, by their keys. Absent without the editor presets' endpoints. */
    replacePreset?(id: string, blocks: readonly string[]): Promise<SavedPreset>;
    /** Renames or recategorises an editor preset, or changes its description. Absent without the editor presets' endpoints. */
    updatePreset?(id: string, details: Partial<PresetDetails>): Promise<SavedPreset>;
    /** Deletes an editor preset. Absent without the editor presets' endpoints. */
    deletePreset?(id: string): Promise<void>;
    /** Where an editor preset downloads as a code preset file. Absent without the editor presets' endpoints. */
    presetExportUrl?(id: string): string;
}

/** What the preview shows the working copy in, to whom and when, and for a snippet, where and with which sample object. */
export interface PreviewRequest extends ShownToAndDate {
    channel: string;
    locale: string;
    /** For a snippet, the placement whose host page shows it, or null for a plain page. */
    placement?: string | null;
    /** For a snippet, the ref of its sample object, or null for the one the preview starts with. */
    sample?: string | null;
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

/** How the API modules reach the server: the browser's fetch, or a fake in tests. */
export type Fetch = (input: string, init?: RequestInit) => Promise<Response>;

export interface Request {
    method?: 'GET' | 'PATCH' | 'POST' | 'PUT' | 'DELETE';
    headers?: Record<string, string>;
    /** JSON as a string, or form data for an upload, whose multipart Content-Type the browser sets. */
    body?: string | FormData;
}

export const defaultFetch: Fetch = (input, init) => fetch(input, init);

/**
 * Sends a request to one of the admin's internal endpoints, and answers with the JSON it answers 200 or 201 with, or
 * undefined for 204 No Content. Any other status throws an ApiError with the server's message, and its validation
 * errors when it gives them.
 */
export async function send(fetchFn: Fetch, url: string, { method = 'GET', headers = {}, body: requestBody }: Request = {}): Promise<unknown> {
    const response = await fetchFn(url, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...headers },
        ...(requestBody === undefined ? {} : { body: requestBody }),
    });

    const body: unknown = await response.json().catch(() => undefined);
    if (response.status !== 200 && response.status !== 201 && response.status !== 204) {
        throw new ApiError(errorMessage(body) ?? `${url} answered ${response.status}.`, response.status, (isObject(body) ? validationErrors(body.errors) : null) ?? []);
    }

    if (response.status === 204) {
        return undefined;
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

        async createPreviewUrl({ channel, locale, shownTo, date, placement, sample }) {
            const body = await request(config.endpoints.previewToken, {
                method: 'POST',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ entry: config.entryUuid, channel, locale, shownTo, date, placement, sample }),
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

        ...(config.endpoints.snippetBlocks === undefined ? {} : { snippetBlocks: snippetBlocksOf(fetchFn, config.endpoints.snippetBlocks) }),
        ...presetsOf(fetchFn, config.entryUuid, config.endpoints.presets, config.endpoints.presetInsert),
        ...editorPresetsOf(fetchFn, config),
    };
}

/**
 * The editor presets' endpoints, all or none: ★ saves blocks as a preset, "Replace <name>" saves over one, and the
 * Presets tab renames, recategorises, deletes and downloads them. Each change needs the CSRF token.
 */
function editorPresetsOf(fetchFn: Fetch, config: BuilderConfig): Pick<BuilderApi, 'createPreset' | 'replacePreset' | 'updatePreset' | 'deletePreset' | 'presetExportUrl'> {
    const { presetCreate, preset, presetExport } = config.endpoints;
    if (presetCreate === undefined || preset === undefined || presetExport === undefined) {
        return {};
    }

    const headers = { 'X-CSRF-Token': config.csrfToken, 'Content-Type': 'application/json' };
    const saved = (body: unknown): SavedPreset => {
        const answer = isObject(body) ? body.preset : undefined;
        if (!isObject(answer) || typeof answer.id !== 'string') {
            throw new ApiError('The answer has no preset.', 200);
        }

        return answer as unknown as SavedPreset;
    };

    return {
        async createPreset(blocks, details) {
            return saved(await send(fetchFn, presetCreate, { method: 'POST', headers, body: JSON.stringify({ entry: config.entryUuid, blocks, ...details }) }));
        },

        async replacePreset(id, blocks) {
            return saved(await send(fetchFn, withUuid(preset, id), { method: 'PUT', headers, body: JSON.stringify({ entry: config.entryUuid, blocks }) }));
        },

        async updatePreset(id, details) {
            return saved(await send(fetchFn, withUuid(preset, id), { method: 'PATCH', headers, body: JSON.stringify(details) }));
        },

        async deletePreset(id) {
            await send(fetchFn, withUuid(preset, id), { method: 'DELETE', headers: { 'X-CSRF-Token': config.csrfToken } });
        },

        presetExportUrl: (id) => withUuid(presetExport, id),
    };
}

/** The Presets tab's endpoints, both or neither. Inserting changes nothing on the server, so it needs no CSRF token. */
function presetsOf(fetchFn: Fetch, entry: string, index: string | undefined, insert: string | undefined): Pick<BuilderApi, 'presets' | 'insertPreset'> {
    if (index === undefined || insert === undefined) {
        return {};
    }

    return {
        async presets(parent) {
            const body = await send(fetchFn, parent === null ? index : `${index}${index.includes('?') ? '&' : '?'}parent=${encodeURIComponent(parent)}`);
            const presets = isObject(body) ? body.presets : undefined;
            if (!Array.isArray(presets)) {
                throw new ApiError('The answer has no presets.', 200);
            }

            const categories = isObject(body) && Array.isArray(body.categories) ? body.categories.filter((category): category is string => typeof category === 'string') : [];

            return { presets: presets as PresetListing[], categories };
        },

        async insertPreset(id) {
            const body = await send(fetchFn, withUuid(insert, id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entry }),
            });
            if (!isObject(body) || !Array.isArray(body.blocks) || !Array.isArray(body.notices)) {
                throw new ApiError('The answer has no blocks.', 200);
            }

            return body as unknown as InsertedPreset;
        },
    };
}

/** The blocks a snippet shows, read from the endpoint, with UUID_PLACEHOLDER for the snippet's UUID. */
function snippetBlocksOf(fetchFn: Fetch, endpoint: string): (snippet: string) => Promise<StoredBlock[]> {
    return async (snippet) => {
        const body = await send(fetchFn, withUuid(endpoint, snippet));
        const blocks = isObject(body) ? body.blocks : undefined;
        if (!Array.isArray(blocks)) {
            throw new ApiError('The answer has no blocks.', 200);
        }

        return blocks as StoredBlock[];
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

/** Whether an endpoint's answer, or a part of it, is a JSON object, which a list isn't. */
export function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
