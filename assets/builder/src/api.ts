import type { Change } from './changeSet';
import type { BuilderConfig } from './config';
import type { EntryResponse, EntryStatus, Registry } from './types';

/** The builder's JSON endpoints. They're internal, and change together with the app. */
export interface BuilderApi {
    fetchRegistry(): Promise<Registry>;
    fetchEntry(): Promise<EntryResponse>;
    saveChanges(changes: readonly Change[]): Promise<EntryStatus>;
    publish(): Promise<EntryStatus>;
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
    method?: 'GET' | 'PATCH' | 'POST';
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

        async saveChanges(changes) {
            const body = await request(config.endpoints.entry, {
                method: 'PATCH',
                headers: { ...write, 'Content-Type': 'application/json' },
                body: JSON.stringify({ changes }),
            });

            return entryStatus(body);
        },

        async publish() {
            return entryStatus(await request(config.endpoints.publish, { method: 'POST', headers: write }));
        },
    };
}

function entryStatus(body: unknown): EntryStatus {
    const status = isObject(body) ? body.status : undefined;
    if (status !== 'published' && status !== 'never_published') {
        throw new ApiError('The answer has no entry status.', 200);
    }

    return status;
}

function errorMessage(body: unknown): string | undefined {
    return isObject(body) && typeof body.error === 'string' ? body.error : undefined;
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
