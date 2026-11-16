import { describe, expect, it, vi } from 'vitest';
import { ApiError, createApi } from '../src/api';
import type { BuilderConfig } from '../src/config';
import { entry, settings } from './fixtures';

const config: BuilderConfig = {
    entryUuid: 'e1',
    locale: 'en_US',
    csrfToken: 'abc123',
    backUrl: '/admin/',
    overviewUrl: '/admin/cms/entry/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
    endpoints: {
        registry: '/admin/cms/api/registry',
        entry: '/admin/cms/api/entries/e1',
        validate: '/admin/cms/api/entries/e1/validate',
        publish: '/admin/cms/api/entries/e1/publish',
        discard: '/admin/cms/api/entries/e1/discard',
        settings: '/admin/cms/api/entries/e1/settings',
        previewToken: '/admin/cms/api/preview/token',
        previewRender: '/admin/cms/api/preview/render',
        presence: '/admin/cms/api/entries/e1/presence',
    },
    channels: [{ code: 'WEB', name: 'Web store' }],
    customerGroups: [],
};

function respond(status: number, body: unknown) {
    return vi.fn(async () => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }));
}

describe('the JSON endpoints', () => {
    it('should send the change set with the lock token, the CSRF token and the session cookie', async () => {
        const fetch = respond(200, { status: 'never_published', lockToken: 4 });
        const changes = [{ op: 'remove_block' as const, key: 'a' }];

        await expect(createApi(config, fetch).saveChanges(changes, 3)).resolves.toEqual({ status: 'never_published', lockToken: 4 });

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1', {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123', 'Content-Type': 'application/json' },
            body: JSON.stringify({ lockToken: 3, changes }),
        });
    });

    it("should answer a save with the colleagues' changes and the clashes when the server merged it", async () => {
        const clashes = [{ kind: 'deleted_block', key: 'a' }];
        const answer = { status: 'unpublished_changes', lockToken: 5, entry: entry(), clashes };

        await expect(createApi(config, respond(200, answer)).saveChanges([], 3)).resolves.toEqual(answer);
    });

    it('should fail when a save answers without a lock token', async () => {
        await expect(createApi(config, respond(200, { status: 'published' })).saveChanges([], 0)).rejects.toBeInstanceOf(ApiError);
    });

    it('should say the editor still has the entry open, and answer with who else does and the lock token', async () => {
        const answer = { editors: [{ name: 'Jane Roe' }], lockToken: 7 };
        const fetch = respond(200, answer);

        await expect(createApi(config, fetch).presence()).resolves.toEqual(answer);

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1/presence', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123' },
        });
    });

    it('should fail when presence answers without the editors', async () => {
        await expect(createApi(config, respond(200, { lockToken: 7 })).presence()).rejects.toBeInstanceOf(ApiError);
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

    it('should answer with the status a save leaves, such as unpublished changes', async () => {
        await expect(createApi(config, respond(200, { status: 'unpublished_changes', lockToken: 1 })).saveChanges([], 0)).resolves.toEqual({
            status: 'unpublished_changes',
            lockToken: 1,
        });
    });

    it('should fail when the answer has a status it does not know', async () => {
        await expect(createApi(config, respond(200, { status: 'scheduled', lockToken: 1 })).saveChanges([], 0)).rejects.toBeInstanceOf(ApiError);
    });

    it('should discard with the CSRF token and answer with the published content', async () => {
        const answer = { entry: entry(), lockToken: 2, settings: settings(), status: 'published', editors: [] };
        const fetch = respond(200, answer);

        await expect(createApi(config, fetch).discard()).resolves.toEqual(answer);

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1/discard', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123' },
        });
    });

    it('should save the settings that changed with the CSRF token, and answer with all of them', async () => {
        const saved = { ...settings(), enabled: false };
        const fetch = respond(200, { settings: saved });

        await expect(createApi(config, fetch).saveSettings({ enabled: false })).resolves.toEqual(saved);

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1/settings', {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123', 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: false }),
        });
    });

    it('should fail with the reason the server refused the settings for', async () => {
        const fetch = respond(400, { error: '"Show until" must come after "Show from"' });

        await expect(createApi(config, fetch).saveSettings({ showUntil: '2026-11-01T00:00:00Z' })).rejects.toEqual(
            new ApiError('"Show until" must come after "Show from"', 400),
        );
    });

    it('should fail when the settings answer has no settings', async () => {
        await expect(createApi(config, respond(200, {})).saveSettings({ enabled: true })).rejects.toBeInstanceOf(ApiError);
    });

    it('should fail with the reason the server refused a publish for', async () => {
        const fetch = respond(422, { error: 'Not on a Sunday' });

        await expect(createApi(config, fetch).publish()).rejects.toEqual(new ApiError('Not on a Sunday', 422));
    });

    it('should fail with the validation errors a refused publish lists', async () => {
        const errors = [{ block: 'a', field: 'text', locale: 'en_US', message: 'This field is required.' }];
        const fetch = respond(422, { error: 'One error needs fixing', errors });

        const refusal = await createApi(config, fetch)
            .publish()
            .catch((error: unknown) => error);

        expect(refusal).toEqual(new ApiError('One error needs fixing', 422, errors));
        expect(refusal).toHaveProperty('errors', errors);
    });

    it('should validate the working copy and answer with its errors, without a CSRF token', async () => {
        const errors = [
            { block: 'a', field: null, locale: null, message: 'Needs at least 2 Column blocks inside.' },
            { block: null, field: 'campaign', locale: null, message: 'This value should not be blank.' },
        ];
        const fetch = respond(200, { errors });

        await expect(createApi(config, fetch).validate()).resolves.toEqual(errors);

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/entries/e1/validate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
    });

    it('should fail when the validation answer has no list of errors', async () => {
        await expect(createApi(config, respond(200, {})).validate()).rejects.toBeInstanceOf(ApiError);
        await expect(createApi(config, respond(200, { errors: [{ block: 'a' }] })).validate()).rejects.toBeInstanceOf(ApiError);
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

        await expect(createApi(config, fetch).saveChanges([], 0)).rejects.toEqual(new ApiError('Invalid CSRF token.', 403));
    });

    it('should fail when the answer is not JSON', async () => {
        const fetch = vi.fn(async () => new Response('<html>Login</html>', { status: 200 }));

        await expect(createApi(config, fetch).fetchEntry()).rejects.toBeInstanceOf(ApiError);
    });
});
