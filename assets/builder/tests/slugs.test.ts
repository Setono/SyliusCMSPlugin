import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createSlugFollower } from '../src/slugs';
import type { BuilderStore } from '../src/store';
import { storeWith } from './fixtures';

/** What Sylius's slug generator makes of a title, near enough. */
const slugify = (title: string): string =>
    title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

function slugOf(store: BuilderStore, locale = 'en_US'): unknown {
    return store.getState().entry.translations[locale]?.fields.slug;
}

/** Changes the title as the title field does, and tells the follower. */
function typeTitle(store: BuilderStore, follower: { titleChanged(locale: string, before: string | null): void }, title: string): void {
    const { locale, entry } = store.getState();
    const before = entry.translations[locale]?.fields.title;
    store.getState().setEntryValue('title', title);
    follower.titleChanged(locale, typeof before === 'string' ? before : null);
}

describe('a slug following its title', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('should generate the slug again from the new title while it is what the old title gives', async () => {
        const store = storeWith();
        const generate = vi.fn(async (title: string) => slugify(title));
        const follower = createSlugFollower(store, generate, { delay: 100 });

        typeTitle(store, follower, 'About our shop');
        await vi.advanceTimersByTimeAsync(100);

        expect(slugOf(store)).toBe('about-our-shop');
        expect(store.getState().unsaved).toContainEqual({ op: 'set_entry_value', field: 'slug', locale: 'en_US', value: 'about-our-shop' });
    });

    it('should generate once for a title typed letter by letter, from the title before the typing', async () => {
        const store = storeWith();
        const generate = vi.fn(async (title: string) => slugify(title));
        const follower = createSlugFollower(store, generate, { delay: 100 });

        typeTitle(store, follower, 'About us!');
        await vi.advanceTimersByTimeAsync(50);
        typeTitle(store, follower, 'About us!!');
        await vi.advanceTimersByTimeAsync(50);
        expect(generate).not.toHaveBeenCalled();
        typeTitle(store, follower, 'About us now');
        await vi.advanceTimersByTimeAsync(100);

        expect(generate.mock.calls).toEqual([['About us'], ['About us now']]);
        expect(slugOf(store)).toBe('about-us-now');
    });

    it('should leave a slug the editor changed', async () => {
        const store = storeWith();
        store.getState().setEntryValue('slug', 'about');
        const follower = createSlugFollower(store, async (title) => slugify(title), { delay: 100 });

        typeTitle(store, follower, 'About our shop');
        await vi.advanceTimersByTimeAsync(100);

        expect(slugOf(store)).toBe('about');
    });

    it('should generate an empty slug from the title', async () => {
        const store = storeWith();
        store.getState().setEntryValue('slug', null);
        store.getState().setEntryValue('title', null);
        const generate = vi.fn(async (title: string) => slugify(title));
        const follower = createSlugFollower(store, generate, { delay: 100 });

        typeTitle(store, follower, 'Contact us');
        await vi.advanceTimersByTimeAsync(100);

        expect(slugOf(store)).toBe('contact-us');
        // An empty title gives an empty slug without asking
        expect(generate.mock.calls).toEqual([['Contact us']]);
    });

    it('should empty the slug with the title', async () => {
        const store = storeWith();
        const follower = createSlugFollower(store, async (title) => slugify(title), { delay: 100 });

        typeTitle(store, follower, '');
        await vi.advanceTimersByTimeAsync(100);

        expect(slugOf(store)).toBeNull();
    });

    it('should set the slug in the locale whose title changed, after the editor switched locale', async () => {
        const store = storeWith();
        const follower = createSlugFollower(store, async (title) => slugify(title), { delay: 100 });

        typeTitle(store, follower, 'About our shop');
        store.getState().setLocale('da_DK');
        await vi.advanceTimersByTimeAsync(100);

        expect(slugOf(store)).toBe('about-our-shop');
        expect(slugOf(store, 'da_DK')).toBeUndefined();
    });

    it('should leave a slug the editor changes while it is generated', async () => {
        const store = storeWith();
        let answer: (slug: string) => void = () => undefined;
        const follower = createSlugFollower(
            store,
            (title) => (title === 'About us' ? Promise.resolve('about-us') : new Promise<string>((resolve) => (answer = resolve))),
            { delay: 100 },
        );

        typeTitle(store, follower, 'About our shop');
        await vi.advanceTimersByTimeAsync(100);
        store.getState().setEntryValue('slug', 'shop');
        answer('about-our-shop');
        await vi.advanceTimersByTimeAsync(0);

        expect(slugOf(store)).toBe('shop');
    });

    it('should follow from the title before the typing when the title changes while the slug is generated', async () => {
        const store = storeWith();
        const answers: Array<() => void> = [];
        const generate = vi.fn((title: string) => new Promise<string>((resolve) => answers.push(() => resolve(slugify(title)))));
        const follower = createSlugFollower(store, generate, { delay: 100 });

        typeTitle(store, follower, 'Team');
        await vi.advanceTimersByTimeAsync(100);
        typeTitle(store, follower, 'Teams of ours');
        answers.splice(0).forEach((resolve) => resolve());
        await vi.advanceTimersByTimeAsync(100);
        answers.splice(0).forEach((resolve) => resolve());
        await vi.advanceTimersByTimeAsync(0);

        expect(generate.mock.calls).toEqual([['About us'], ['Team'], ['About us'], ['Teams of ours']]);
        expect(slugOf(store)).toBe('teams-of-ours');
    });

    it('should leave the slug as it is when it cannot be generated, and follow again on the next change', async () => {
        const store = storeWith();
        const generate = vi.fn(async (title: string) => slugify(title));
        generate.mockRejectedValueOnce(new Error('Offline'));
        const follower = createSlugFollower(store, generate, { delay: 100 });

        typeTitle(store, follower, 'About our shop');
        await vi.advanceTimersByTimeAsync(100);
        expect(slugOf(store)).toBe('about-us');

        typeTitle(store, follower, 'About our shops');
        await vi.advanceTimersByTimeAsync(100);
        expect(generate).toHaveBeenLastCalledWith('About our shops');
        expect(slugOf(store)).toBe('about-our-shops');
    });

    it('should make no edit when the slug the new title gives is the slug it has', async () => {
        const store = storeWith();
        const follower = createSlugFollower(store, async (title) => slugify(title), { delay: 100 });

        typeTitle(store, follower, 'About us!');
        await vi.advanceTimersByTimeAsync(100);

        expect(store.getState().unsaved).toEqual([{ op: 'set_entry_value', field: 'title', locale: 'en_US', value: 'About us!' }]);
    });

    it('should stop following once disposed', async () => {
        const store = storeWith();
        const generate = vi.fn(async (title: string) => slugify(title));
        const follower = createSlugFollower(store, generate, { delay: 100 });

        typeTitle(store, follower, 'About our shop');
        follower.dispose();
        await vi.advanceTimersByTimeAsync(100);

        expect(generate).not.toHaveBeenCalled();
        expect(slugOf(store)).toBe('about-us');
    });
});
