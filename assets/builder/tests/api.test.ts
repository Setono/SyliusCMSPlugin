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
        previewToken: '/admin/cms/api/preview/token',
        previewRender: '/admin/cms/api/preview/render',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
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

    it('should ask for a preview token for the entry in a channel and a locale, and answer with the preview URL', async () => {
        const fetch = respond(200, { url: 'http://admin.example/en_US/about-us?_cms_preview=abc.def' });

        await expect(createApi(config, fetch).createPreviewUrl({ channel: 'WEB', locale: 'en_US' })).resolves.toBe(
            'http://admin.example/en_US/about-us?_cms_preview=abc.def',
        );

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/preview/token', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123', 'Content-Type': 'application/json' },
            body: JSON.stringify({ entry: 'e1', channel: 'WEB', locale: 'en_US' }),
        });
    });

    it('should fail when the preview token answer has no URL', async () => {
        await expect(createApi(config, respond(200, {})).createPreviewUrl({ channel: 'WEB', locale: 'en_US' })).rejects.toBeInstanceOf(ApiError);
    });

    it('should ask for blocks of the working copy to be rendered under the preview token, and answer their HTML by key', async () => {
        const fetch = respond(200, { blocks: { a: '<!--cms:block a heading--><h2>Hello</h2><!--/cms:block a-->' } });
        const blocks = [{ key: 'a', type: 'heading', version: 1, style: null, fields: { text: { en_US: 'Hello' } }, translated: {}, conditions: [], children: [] }];

        await expect(createApi(config, fetch).renderPreview('abc.def+/', blocks)).resolves.toEqual({ a: '<!--cms:block a heading--><h2>Hello</h2><!--/cms:block a-->' });

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/preview/render?_cms_preview=abc.def%2B%2F', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123', 'Content-Type': 'application/json' },
            body: JSON.stringify({ blocks }),
        });
    });

    it('should fail when the rendered blocks are missing from the answer', async () => {
        await expect(createApi(config, respond(200, { blocks: { a: 1 } })).renderPreview('t', [])).rejects.toBeInstanceOf(ApiError);
        await expect(createApi(config, respond(200, {})).renderPreview('t', [])).rejects.toBeInstanceOf(ApiError);
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
