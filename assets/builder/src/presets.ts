/**
 * The block picker's Presets tab: the presets grouped by category, searched, and rendered live in a document of their
 * own with the preview page's stylesheets. Pure functions, with no React. See "Presets" in
 * docs/spec/layouts-and-presets.md and "Block picker" in docs/spec/builder.md
 */
import type { PresetListing } from './types';

/** The presets of one category, or of none. */
export interface PresetGroup {
    category: string | null;
    presets: PresetListing[];
}

export interface GroupedPresets {
    /** The presets offered at the spot, by category in the order the categories come, those without one last. */
    groups: PresetGroup[];
    /** The presets left out at the spot, each with why. */
    leftOut: PresetListing[];
}

/** What the preset renders need of the page the preview shows: its address, and its stylesheets as HTML. */
export interface PreviewPage {
    base: string;
    stylesheets: string;
}

/** The presets that match the search, in their name, category or description, ignoring case. */
export function groupPresets(presets: readonly PresetListing[], query: string): GroupedPresets {
    const search = query.trim().toLocaleLowerCase();
    const matching = presets.filter((preset) => search === '' || [preset.name, preset.category, preset.description].some((text) => text?.toLocaleLowerCase().includes(search)));

    const byCategory = new Map<string | null, PresetListing[]>();
    for (const preset of matching.filter(({ fits }) => fits)) {
        byCategory.set(preset.category, [...(byCategory.get(preset.category) ?? []), preset]);
    }

    const groups = [...byCategory].map(([category, grouped]) => ({ category, presets: grouped }));

    return {
        groups: [...groups.filter(({ category }) => category !== null), ...groups.filter(({ category }) => category === null)],
        leftOut: matching.filter(({ fits }) => !fits),
    };
}

/**
 * The document a preset renders in: the preview page's stylesheets, at its address so their URLs resolve, around the
 * blocks' HTML in the element a layout wraps its tree in. It runs no scripts.
 */
export function presetDocument(page: PreviewPage, html: string): string {
    return `<!doctype html><html><head><meta charset="utf-8"><base href="${escapeAttribute(page.base)}">${page.stylesheets}</head><body><div class="cms-tree">${html}</div></body></html>`;
}

/** The stylesheets of the page the preview shows, linked or inline, and its address. */
export function stylesheetsOf(document: Document): PreviewPage {
    return {
        base: document.baseURI,
        stylesheets: [...(document.head?.querySelectorAll('link[rel~="stylesheet"], style') ?? [])].map((element) => element.outerHTML).join(''),
    };
}

function escapeAttribute(value: string): string {
    return value.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;');
}
