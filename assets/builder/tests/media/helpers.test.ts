import { describe, expect, it, vi } from 'vitest';
import { createMediaApi, listQuery } from '../../src/media/api';
import { flattenTree, folderAndDescendants, folderTree } from '../../src/media/folders';
import { ALL_MEDIA, UNSORTED } from '../../src/media/types';
import { checkFile, formatSize, typeNames } from '../../src/media/upload';
import { file, libraryConfig, mediaItem } from './fixtures';

const folders = [
    { uuid: 'summer', name: 'Summer', parent: 'campaigns' },
    { uuid: 'campaigns', name: 'Campaigns', parent: null },
    { uuid: 'autumn', name: 'Autumn', parent: 'campaigns' },
    { uuid: 'brand', name: 'Brand', parent: null },
    { uuid: 'orphan', name: 'Orphan', parent: 'deleted' },
];

describe('the folder tree', () => {
    it('should nest folders under their parents, each level by name', () => {
        expect(flattenTree(folderTree(folders)).map(({ folder, depth }) => `${'-'.repeat(depth)}${folder.name}`)).toEqual([
            'Brand',
            'Campaigns',
            '-Autumn',
            '-Summer',
            'Orphan',
        ]);
    });

    it('should give a folder and the folders inside it, which it cannot be moved into', () => {
        expect([...folderAndDescendants(folders, 'campaigns')].sort()).toEqual(['autumn', 'campaigns', 'summer']);
        expect([...folderAndDescendants(folders, 'brand')]).toEqual(['brand']);
    });
});

describe('checking a file before uploading it', () => {
    it('should write sizes as the server does', () => {
        expect(formatSize(900)).toBe('900 B');
        expect(formatSize(1536)).toBe('1.5 KB');
        expect(formatSize(20 * 1024 * 1024)).toBe('20 MB');
        expect(formatSize(2.25 * 1024 * 1024)).toBe('2.3 MB');
    });

    it('should refuse a file over the effective upload limit', () => {
        expect(checkFile(file('poster.png', 'image/png', 3 * 1024 * 1024), libraryConfig.media)).toEqual({ reason: 'too_big', size: 3 * 1024 * 1024, limit: 2 * 1024 * 1024 });
        expect(checkFile(file('poster.png', 'image/png', 2 * 1024 * 1024), libraryConfig.media)).toBeNull();
    });

    it("should refuse a type the library doesn't take, and leave one the browser can't tell to the server", () => {
        expect(checkFile(file('notes.txt', 'text/plain'), libraryConfig.media)).toEqual({ reason: 'not_allowed' });
        expect(checkFile(file('clip.mp4', 'video/mp4'), libraryConfig.media)).toBeNull();
        expect(checkFile(file('mystery', ''), libraryConfig.media)).toBeNull();
    });

    it('should name the types the library takes as editors know them', () => {
        expect(typeNames(libraryConfig.media)).toEqual(['JPEG', 'PNG', 'SVG', 'MP4', 'PDF']);
        expect(typeNames({ maxUploadSize: 1, types: { image: [], video: [], document: ['application/zip'] } })).toEqual(['ZIP']);
    });
});

describe('the media endpoints', () => {
    it('should ask for the open folder, the search, the kind, the missing-alt filter and the page', () => {
        expect(listQuery({ folder: ALL_MEDIA, search: '', kind: null, missingAlt: false }, 1)).toBe('page=1');
        expect(listQuery({ folder: UNSORTED, search: ' dog ', kind: 'image', missingAlt: true }, 2)).toBe('folder=unsorted&q=dog&kind=image&missingAlt=1&page=2');
        expect(listQuery({ folder: 'campaigns', search: '', kind: null, missingAlt: false }, 1)).toBe('folder=campaigns&page=1');
    });

    it('should ask a picker for the kinds its field takes, unless one kind is picked', () => {
        expect(listQuery({ folder: ALL_MEDIA, search: '', kind: null, missingAlt: false, kinds: ['image', 'document'] }, 1)).toBe('kind=image%2Cdocument&page=1');
        expect(listQuery({ folder: ALL_MEDIA, search: '', kind: 'document', missingAlt: false, kinds: ['image', 'document'] }, 1)).toBe('kind=document&page=1');
    });

    it('should find media items by their UUIDs, 60 at a time', async () => {
        const uuids = Array.from({ length: 61 }, (_, index) => `uuid-${String(index)}`);
        const fetch = vi.fn(async (url: string) => {
            const asked = new URL(url, 'https://shop.example').searchParams.get('uuids')?.split(',') ?? [];

            return new Response(JSON.stringify({ items: asked.slice(0, 1).map((uuid) => mediaItem(uuid)), page: 1, pages: 1, total: 1 }), { status: 200 });
        });

        const found = await createMediaApi(libraryConfig, fetch).find(uuids);

        expect(found.map(({ uuid }) => uuid)).toEqual(['uuid-0', 'uuid-60']);
        expect(fetch).toHaveBeenCalledTimes(2);
        expect(fetch.mock.calls[0]?.[0]).toBe(`/admin/cms/api/media?uuids=${uuids.slice(0, 60).join('%2C')}`);
    });

    it('should upload files as files[] in a form, into a folder, with the CSRF token', async () => {
        const answer = { created: [mediaItem('a')], duplicates: [], refused: [] };
        const fetch = vi.fn(async () => new Response(JSON.stringify(answer), { status: 200 }));

        await expect(createMediaApi(libraryConfig, fetch).upload([file('a.png', 'image/png'), file('b.png', 'image/png')], 'campaigns', true)).resolves.toEqual(answer);

        const [url, init] = fetch.mock.calls[0] as unknown as [string, RequestInit];
        expect(url).toBe('/admin/cms/api/media');
        expect(init.method).toBe('POST');
        expect(init.headers).toEqual({ Accept: 'application/json', 'X-CSRF-Token': 'abc123' });
        const body = init.body as FormData;
        expect(body.getAll('files[]').map((value) => (value as File).name)).toEqual(['a.png', 'b.png']);
        expect(body.get('folder')).toBe('campaigns');
        expect(body.get('copy')).toBe('1');
    });

    it("should save a media item's changes as JSON, and fail with the server's reason", async () => {
        const fetch = vi.fn(async () => new Response(JSON.stringify(mediaItem('a', { name: 'Dog' })), { status: 200 }));

        await createMediaApi(libraryConfig, fetch).save('a', { name: 'Dog', focalPoint: { x: 0.2, y: 0.3 } });

        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/media/a', {
            credentials: 'same-origin',
            method: 'PATCH',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123', 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: 'Dog', focalPoint: { x: 0.2, y: 0.3 } }),
        });

        const refusing = vi.fn(async () => new Response(JSON.stringify({ error: 'A media item needs a name' }), { status: 400 }));
        await expect(createMediaApi(libraryConfig, refusing).save('a', { name: '' })).rejects.toThrow('A media item needs a name');
    });

    it('should take a created folder, which answers 201', async () => {
        const fetch = vi.fn(async () => new Response(JSON.stringify({ uuid: 'f', name: 'Campaigns', parent: null }), { status: 201 }));

        await expect(createMediaApi(libraryConfig, fetch).createFolder('Campaigns', null)).resolves.toEqual({ uuid: 'f', name: 'Campaigns', parent: null });
    });

    it('should ask only for the media items nothing uses', () => {
        expect(listQuery({ folder: ALL_MEDIA, search: '', kind: 'image', missingAlt: false, unused: true }, 1)).toBe('kind=image&unused=1&page=1');
    });

    it("should read a media item's uses", async () => {
        const uses = [{ entry: 'e', title: 'Summer sale', contentType: 'Page', url: '/admin/cms/entry/e', draft: true, published: true }];
        const fetch = vi.fn(async (_url: string) => new Response(JSON.stringify({ uses }), { status: 200 }));

        await expect(createMediaApi(libraryConfig, fetch).uses('a')).resolves.toEqual(uses);
        expect(fetch.mock.calls[0]?.[0]).toBe('/admin/cms/api/media/a/uses');
    });

    it("should replace a media item's file with the file in a form, with the CSRF token, and fail with the server's reason", async () => {
        const replaced = mediaItem('a', { originalFilename: 'summer-2027.png' });
        const fetch = vi.fn(async () => new Response(JSON.stringify(replaced), { status: 200 }));

        await expect(createMediaApi(libraryConfig, fetch).replace('a', file('summer-2027.png', 'image/png'))).resolves.toEqual(replaced);

        const [url, init] = fetch.mock.calls[0] as unknown as [string, RequestInit];
        expect(url).toBe('/admin/cms/api/media/a/file');
        expect(init.method).toBe('POST');
        expect(init.headers).toEqual({ Accept: 'application/json', 'X-CSRF-Token': 'abc123' });
        expect(((init.body as FormData).get('file') as File).name).toBe('summer-2027.png');

        const refusing = vi.fn(async () => new Response(JSON.stringify({ error: "prices.pdf isn't an image." }), { status: 400 }));
        await expect(createMediaApi(libraryConfig, refusing).replace('a', file('prices.pdf', 'application/pdf'))).rejects.toThrow("prices.pdf isn't an image.");
    });

    it("should delete a media item, which answers 204 without a body, and fail with the server's reason", async () => {
        const fetch = vi.fn(async () => new Response(null, { status: 204 }));

        await expect(createMediaApi(libraryConfig, fetch).remove('a')).resolves.toBeUndefined();
        expect(fetch).toHaveBeenCalledWith('/admin/cms/api/media/a', {
            credentials: 'same-origin',
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-Token': 'abc123' },
        });

        const vetoing = vi.fn(async () => new Response(JSON.stringify({ error: 'The logo stays' }), { status: 422 }));
        await expect(createMediaApi(libraryConfig, vetoing).remove('a')).rejects.toThrow('The logo stays');
    });
});
