import { readDataConfig } from './dataConfig';
import type { MediaLibraryConfig } from './media/config';

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

/** One of a snippet's placements, which are managed on its overview page and only listed in the builder. */
export interface SnippetPlacement {
    uuid: string;
    /** The slot's identifier, such as shop.footer. */
    slot: string;
    /** The slot's label in the admin user's locale, or its identifier when the code no longer declares it. */
    label: string;
    /** Whether the code no longer declares the slot, so the placement shows nothing. */
    removed: boolean;
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
        /** POST schedules a publish, and DELETE cancels it. */
        schedule: string;
        settings: string;
        previewToken: string;
        previewRender: string;
        presence: string;
        /** Makes a slug of a title with Sylius's slug generator. Without it, a slug doesn't follow its title. */
        slug?: string;
        /** The entry's URL warnings and old URLs, which the URL & SEO tab shows. */
        urls?: string;
        /** What pickers search, with __kind__ for the kind, such as product. Without it, nothing can be searched. */
        search?: string;
        /** How many items a source yields for a list field's parameters, under a preview token. Without it, no count shows. */
        sourceCount?: string;
        /** The displays the Entries block offers for what its entries field holds, under a preview token. */
        cardDisplays?: string;
        /** The blocks a snippet shows, with UUID_PLACEHOLDER for its UUID, which Detach to edit here copies. Without it, there's no Detach. */
        snippetBlocks?: string;
        /** Another entry's builder, with UUID_PLACEHOLDER for its UUID, which Edit snippet opens. Without it, there's no Edit snippet. */
        builder?: string;
        /** What the top bar shows of a snippet's preview, under a preview token. Without it, the top bar shows nothing of it. */
        previewSnippet?: string;
        /** The sample objects a snippet's preview can show it with, under a preview token. Without it, the sample object can't be picked. */
        previewSamples?: string;
        /** The block presets at a spot in the entry's tree, which takes &parent=<key> for a block's children. Without it, there's no Presets tab. */
        presets?: string;
        /** A preset's blocks ready to insert, with UUID_PLACEHOLDER for its id. Without it, there's no Presets tab. */
        presetInsert?: string;
        /** Saves blocks as a new editor preset. Without it, or the next two, blocks can't be saved as presets, nor presets changed. */
        presetCreate?: string;
        /** An editor preset, with UUID_PLACEHOLDER for its UUID: PUT replaces its blocks, PATCH renames it and DELETE deletes it. */
        preset?: string;
        /** An editor preset as a code preset file to download, with UUID_PLACEHOLDER for its UUID. */
        presetExport?: string;
    };
    /** The store's channels. The settings pick the entry's channels from them, and the preview picks from the entry's. */
    channels: Channel[];
    /** The locales of the store's channels. The locale switcher offers those of the entry's channels. */
    locales?: Locale[];
    /** The store's customer groups, for the audience and Shown to. */
    customerGroups: CustomerGroup[];
    /**
     * The time zone scheduled publishes are entered and shown in, PHP's default time zone on the server, which the admin
     * shows times in, such as Europe/Copenhagen, whatever the editor's own is.
     */
    timeZone: string;
    /** Where the preview's Shown to and Date start, from the entry's audience and date window. Guests and now without it. */
    preview?: ShownToAndDate;
    /** A snippet's placements, by slot, which the More tab lists. Null or absent for an entry that isn't a snippet. */
    placements?: SnippetPlacement[] | null;
    /** What the server tells the editor once the builder opens, such as what creating from a starting point left out. Optional. */
    notices?: string[];
    /** UI strings needed before the registry arrives, such as the loading text. Optional. */
    strings?: Record<string, string>;
    /**
     * What the media picker needs beside the upload limit and types the registry gives: the media library's endpoints and
     * the store's locales, which media items have alt text in. Without it, media fields can't be picked.
     */
    media?: Pick<MediaLibraryConfig, 'endpoints' | 'locales'>;
}

/** What the endpoints that name another entry hold in place of its UUID. */
export const UUID_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

/** An endpoint that names another entry, for the entry with the UUID. */
export function withUuid(endpoint: string, uuid: string): string {
    return endpoint.replace(UUID_PLACEHOLDER, encodeURIComponent(uuid));
}

export function readConfig(element: HTMLElement): BuilderConfig {
    return readDataConfig<BuilderConfig>(element, 'The builder needs its configuration in the data-config attribute.');
}
