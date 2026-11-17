import { describe, expect, it, vi } from 'vitest';
import { ApiError } from '../src/api';
import { createSearchApi } from '../src/search/api';
import { createKnownRefs, knownKey } from '../src/search/known';

function answering(status: number, body: unknown) {
    return vi.fn(async () => new Response(JSON.stringify(body), { status }));
}

describe('the search endpoint', () => {
    it('should search a kind for what was typed', async () => {
        const fetchFn = answering(200, [{ ref: 'T_SHIRT', name: 'T-shirt', thumbnail: null }]);

        const found = await createSearchApi('/admin/cms/api/search/__kind__', fetchFn).search('product', 'red shirt');

        expect(found).toEqual([{ ref: 'T_SHIRT', name: 'T-shirt', thumbnail: null }]);
        expect(fetchFn).toHaveBeenCalledWith('/admin/cms/api/search/product?q=red+shirt', expect.objectContaining({ credentials: 'same-origin' }));
    });

    it('should look refs up in one request, and none for no refs', async () => {
        const fetchFn = answering(200, []);
        const api = createSearchApi('/admin/cms/api/search/__kind__', fetchFn);

        await api.find('app_store', ['aarhus', 'copenhagen']);
        expect(await api.find('app_store', [])).toEqual([]);

        expect(fetchFn).toHaveBeenCalledOnce();
        expect(fetchFn).toHaveBeenCalledWith('/admin/cms/api/search/app_store?refs=aarhus%2Ccopenhagen', expect.anything());
    });

    it('should fail on an answer that is not a list of results', async () => {
        await expect(createSearchApi('/search/__kind__', answering(404, { error: 'There is no search for "spaceship"' })).search('spaceship', '')).rejects.toBeInstanceOf(ApiError);
        await expect(createSearchApi('/search/__kind__', answering(200, [{ ref: 'T_SHIRT' }])).search('product', '')).rejects.toBeInstanceOf(ApiError);
    });
});

describe('what pickers know of the things fields hold', () => {
    it('should look up what is not known yet once, and remember what was not found as nothing', async () => {
        const find = vi.fn(async () => [{ ref: 'MUG', name: 'Mug', thumbnail: null }]);
        const known = createKnownRefs({ find });

        known.getState().request([knownKey('product', 'MUG'), knownKey('product', 'DELETED'), knownKey('product', 'MUG')]);
        known.getState().request([knownKey('product', 'MUG')]);
        await vi.waitFor(() => expect(known.getState().items[knownKey('product', 'MUG')]).toEqual({ ref: 'MUG', name: 'Mug', thumbnail: null }));

        expect(find).toHaveBeenCalledOnce();
        expect(find).toHaveBeenCalledWith('product', ['MUG', 'DELETED']);
        expect(known.getState().items[knownKey('product', 'DELETED')]).toBeNull();
    });

    it('should remember what was picked without looking it up', () => {
        const find = vi.fn(async () => []);
        const known = createKnownRefs({ find });

        known.getState().remember({ [knownKey('taxon', 'SHIRTS')]: { ref: 'SHIRTS', name: 'Shirts', thumbnail: null } });
        known.getState().request([knownKey('taxon', 'SHIRTS')]);

        expect(find).not.toHaveBeenCalled();
        expect(known.getState().items[knownKey('taxon', 'SHIRTS')]?.name).toBe('Shirts');
    });

    it('should ask again after a lookup failed', async () => {
        const find = vi.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue([]);
        const known = createKnownRefs({ find });

        known.getState().request([knownKey('product', 'MUG')]);
        await vi.waitFor(() => expect(find).toHaveBeenCalledOnce());

        // Once the failure is in, the next request asks again
        await vi.waitFor(() => {
            known.getState().request([knownKey('product', 'MUG')]);
            expect(find).toHaveBeenCalledTimes(2);
        });
    });
});
