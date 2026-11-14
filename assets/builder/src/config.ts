/** A channel the entry is in, which the preview can show it in. */
export interface Channel {
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
        publish: string;
        discard: string;
        previewToken: string;
    };
    /** The entry's channels, for the preview channel picker. */
    channels: Channel[];
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
