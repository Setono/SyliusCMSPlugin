/** A channel of the store. */
export interface Channel {
    code: string;
    name: string;
    /** The codes of its locales, its default locale first. */
    locales?: string[];
}

/** A locale of the store's channels, which the locale switcher offers. */
export interface Locale {
    code: string;
    /** In the admin user's locale. */
    name: string;
    /** The locale itself, then the locales its translatable fields fall back through, ending at the store's fallback locale. */
    fallbackChain: string[];
}

/** A customer group of the store, which an entry's audience can name. */
export interface CustomerGroup {
    code: string;
    name: string;
}

/** Who the preview shows the page to, and when. See "Shown to and Date" in docs/spec/targeting.md. */
export interface ShownToAndDate {
    /** A customer group code, '@guest' or '@no_group'. */
    shownTo: string;
    /** A UTC date and time, such as 2026-12-01T00:00:00Z, or null for now. */
    date: string | null;
}

/** What the builder page passes to the app, as JSON in the data-config attribute of #cms-builder. */
export interface BuilderConfig {
    entryUuid: string;
    /** The locale the builder opens in. It edits one locale at a time. */
    locale: string;
    /** The key of the block it opens with selected, such as where a problem is fixed, or null. Optional. */
    block?: string | null;
    csrfToken: string;
    /** Where the back button and Esc go: where the builder was opened from. */
    backUrl: string;
    /** The entry's overview page, which the settings' More tab links to. */
    overviewUrl: string;
    endpoints: {
        registry: string;
        entry: string;
        validate: string;
        publish: string;
        discard: string;
        settings: string;
        previewToken: string;
        previewRender: string;
        presence: string;
        /** Makes a slug of a title with Sylius's slug generator. Without it, a slug doesn't follow its title. */
        slug?: string;
        /** The entry's URL warnings and old URLs, which the URL & SEO tab shows. */
        urls?: string;
    };
    /** The store's channels. The settings pick the entry's channels from them, and the preview picks from the entry's. */
    channels: Channel[];
    /** The locales of the store's channels. The locale switcher offers those of the entry's channels. */
    locales?: Locale[];
    /** The store's customer groups, for the audience and Shown to. */
    customerGroups: CustomerGroup[];
    /** Where the preview's Shown to and Date start, from the entry's audience and date window. Guests and now without it. */
    preview?: ShownToAndDate;
    /** UI strings needed before the registry arrives, such as the loading text. Optional. */
    strings?: Record<string, string>;
}

export function readConfig(element: HTMLElement): BuilderConfig {
    const json = element.dataset.config;
    if (json === undefined || json === '') {
        throw new Error('The builder needs its configuration in the data-config attribute.');
    }

    return JSON.parse(json) as BuilderConfig;
}
