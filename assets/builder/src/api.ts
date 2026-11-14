import type { Change } from './changeSet';
import type { BuilderConfig } from './config';
import type { EntryResponse, EntrySettings, EntryStatus, PresenceResponse, Registry, SaveResponse, StoredBlock } from './types';

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
    /** Fails with an ApiError of status 422 and the reason when the server refuses, such as a listener's veto. */
    publish(): Promise<EntryStatus>;
    /** Deletes the draft, and answers with the working copy that's left: the published content. */
    discard(): Promise<EntryResponse>;
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

/** What the preview shows the working copy in. Shown to, Date, the sample object and the placement come later. */
export interface PreviewRequest {
    channel: string;
    locale: string;
}

export class ApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

type Fetch = (input: string, init?: RequestInit) => Promise<Response>;

interface Request {
    method?: 'GET' | 'PATCH' | 'POST' | 'PUT';
    headers?: Record<string, string>;
    body?: string;
}

export function createApi(config: BuilderConfig, fetchFn: Fetch = (input, init) => fetch(input, init)): BuilderApi {
    const request = async (url: string, { method = 'GET', headers = {}, body: requestBody }: Request = {}): Promise<unknown> => {
        const response = await fetchFn(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...headers },
            ...(requestBody === undefined ? {} : { body: requestBody }),
        });

        const body: unknown = await response.json().catch(() => undefined);
        if (response.status !== 200) {
            throw new ApiError(errorMessage(body) ?? `${url} answered ${response.status}.`, response.status);
        }

        if (body === undefined) {
            throw new ApiError(`${url} didn't answer with JSON.`, response.status);
        }

        return body;
    };

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

        async publish() {
            return entryStatus(await request(config.endpoints.publish, { method: 'POST', headers: write }));
        },

        async discard() {
            return (await request(config.endpoints.discard, { method: 'POST', headers: write })) as EntryResponse;
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

        async createPreviewUrl({ channel, locale }) {
            const body = await request(config.endpoints.previewToken, {
                method: 'POST',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ entry: config.entryUuid, channel, locale }),
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

const statuses: readonly unknown[] = ['never_published', 'published', 'unpublished_changes'] satisfies EntryStatus[];

function entryStatus(body: unknown): EntryStatus {
    const status = isObject(body) ? body.status : undefined;
    if (!statuses.includes(status)) {
        throw new ApiError('The answer has no entry status.', 200);
    }

    return status as EntryStatus;
}

function errorMessage(body: unknown): string | undefined {
    return isObject(body) && typeof body.error === 'string' ? body.error : undefined;
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
