/**
 * A slug follows its title until the editor changes it: while a translation's slug is empty, or is what Sylius's slug
 * generator makes of its title, changing the title generates the slug again. See "Slugs" in docs/spec/urls-and-seo.md.
 */
import type { BuilderStore } from './store';

export interface SlugFollower {
    /** Says the editor changed the title in a locale, which had the title before until then. */
    titleChanged(locale: string, before: string | null): void;
    dispose(): void;
}

export interface SlugFollowerOptions {
    /** How long after the title stops changing the slug follows, in milliseconds. */
    delay?: number;
}

/** A title the editor is changing, since before, and the slug's next follow. */
interface Changing {
    before: string;
    timer?: ReturnType<typeof setTimeout>;
}

/**
 * The slug follows a moment after the title stops changing, one locale at a time, so it's generated once for a word
 * typed, and from the title the slug had before the editor started typing. The slug set is an edit like any other, in
 * the locale whose title changed. It doesn't follow when the editor changed the slug or the title in the meantime, and
 * an emptied title empties the slug.
 */
export function createSlugFollower(store: BuilderStore, generate: (title: string) => Promise<string>, { delay = 300 }: SlugFollowerOptions = {}): SlugFollower {
    const changing = new Map<string, Changing>();
    let queue: Promise<void> = Promise.resolve();
    let disposed = false;

    const value = (locale: string, field: string): string => {
        const current = store.getState().entry.translations[locale]?.fields[field];

        return typeof current === 'string' ? current : '';
    };

    const slugOf = async (title: string): Promise<string> => (title === '' ? '' : generate(title));

    const follow = async (locale: string): Promise<void> => {
        const title = changing.get(locale);
        if (title === undefined || disposed) {
            return;
        }

        const slug = value(locale, 'slug');
        const now = value(locale, 'title');
        const [generatedBefore, generated] = await Promise.all([slugOf(title.before), slugOf(now)]);

        // The title changed again, so the next follow takes over, still from the title before
        if (disposed || value(locale, 'title') !== now) {
            return;
        }

        changing.delete(locale);
        if (value(locale, 'slug') !== slug || (slug !== '' && slug !== generatedBefore) || generated === slug) {
            return;
        }

        store.getState().setEntryValue('slug', generated === '' ? null : generated, locale);
    };

    return {
        titleChanged(locale, before) {
            const title = changing.get(locale) ?? { before: before ?? '' };
            clearTimeout(title.timer);
            title.timer = setTimeout(() => {
                // The slug stays as it is when it can't be generated, and follows on the title's next change, from the
                // title before
                queue = queue.then(() => follow(locale)).catch(() => undefined);
            }, delay);
            changing.set(locale, title);
        },

        dispose() {
            disposed = true;
            changing.forEach(({ timer }) => clearTimeout(timer));
            changing.clear();
        },
    };
}
