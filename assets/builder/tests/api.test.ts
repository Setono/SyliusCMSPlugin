import { describe, expect, it, vi } from 'vitest';
import { ApiError, createApi } from '../src/api';
import type { BuilderConfig } from '../src/config';

const config: BuilderConfig = {
    entryUuid: 'e1',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    endpoints: {
        registry: '/admin/cms/api/registry',
        entry: '/admin/cms/api/entries/e1',
        publish: '/admin/cms/api/entries/e1/publish',
    },
};

function respond(status: number, body: unknown) {
    return vi.fn(async () => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }));
}

describe('the JSON endpoints', () => {
    it('should send the change set with the CSRF token and the session cookie', async () => {
        const fetch = respond(200, { status: 'never_published' });
        const changes = [{ op: 'remove_block' as const, key: 'a' }];

        await expect(createApi(config, fetch).saveChanges(changes)).resolves.toBe('never_published');

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1', {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123', 'Content-Type': 'application/json' },
            body: JSON.stringify({ changes }),
        });
    });

    it('should publish with the CSRF token and an empty body', async () => {
        const fetch = respond(200, { status: 'published' });

        await expect(createApi(config, fetch).publish()).resolves.toBe('published');

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1/publish', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123' },
        });
    });

    it('should fail with the error the server gives', async () => {
        const fetch = respond(403, { error: 'Invalid CSRF token.' });

        await expect(createApi(config, fetch).saveChanges([])).rejects.toEqual(new ApiError('Invalid CSRF token.', 403));
    });

    it('should fail when the answer is not JSON', async () => {
        const fetch = vi.fn(async () => new Response('<html>Login</html>', { status: 200 }));

        await expect(createApi(config, fetch).fetchEntry()).rejects.toBeInstanceOf(ApiError);
    });
});
