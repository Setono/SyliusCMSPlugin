/**
 * The kinds of item the Product, Products, Taxon, Taxons, Entry and Entries fields hold, by code, and what their
 * controls say about them. The kind is also what the search endpoint searches. See "Shipped field types" in
 * docs/spec/extending.md
 */
import type { MessageKey } from '../i18n';
import type { FieldSchema } from '../types';
import { isJsonObject } from '../values';

export interface CatalogKind {
    /** What the search endpoint searches, and the type of reference the field stores: product, taxon or entry. */
    kind: string;
    /** The list control's add button. */
    add: MessageKey;
    /** The search box's name. */
    search: MessageKey;
}

const PRODUCT: CatalogKind = { kind: 'product', add: 'setono_sylius_cms.builder.items.add_product', search: 'setono_sylius_cms.builder.items.search_products' };
const TAXON: CatalogKind = { kind: 'taxon', add: 'setono_sylius_cms.builder.items.add_taxon', search: 'setono_sylius_cms.builder.items.search_taxons' };
const ENTRY: CatalogKind = { kind: 'entry', add: 'setono_sylius_cms.builder.items.add_entry', search: 'setono_sylius_cms.builder.items.search_entries' };

/** The kind of item a field holds, by its field type: one item, or a list of them. */
const CATALOG_KINDS: Readonly<Record<string, CatalogKind>> = { product: PRODUCT, products: PRODUCT, taxon: TAXON, taxons: TAXON, entry: ENTRY, entries: ENTRY };

export function catalogKindOf(schema: FieldSchema): CatalogKind {
    return CATALOG_KINDS[schema['x-cms-type']] ?? PRODUCT;
}

/**
 * What narrows the search of a field's items: an Entry or Entries field that names a content type, such as a blog
 * post's categories, searches only its entries. Undefined when nothing does.
 */
export function catalogParametersOf(schema: FieldSchema): Readonly<Record<string, string>> | undefined {
    const options = isJsonObject(schema['x-cms-options']) ? schema['x-cms-options'] : {};

    return typeof options.contentType === 'string' ? { contentType: options.contentType } : undefined;
}

