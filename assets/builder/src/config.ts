/** A channel of the store. */
export interface Channel {
    code: string;
    name: string;
}

/** A customer group of the store, which an entry's audience can name. */
export interface CustomerGroup {
    code: string;
    name: string;
}

/** What the builder page passes to the app, as JSON in the data-config attribute of #cms-builder. */
export interface BuilderConfig {
    entryUuid: string;
    /** The one locale the builder edits. */
    locale: string;
    csrfToken: string;
    backUrl: string;
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
    };
    /** The store's channels. The settings pick the entry's channels from them, and the preview picks from the entry's. */
    channels: Channel[];
    /** The store's customer groups, for the audience. */
    customerGroups: CustomerGroup[];
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
