/**
 * The kinds of item the Product, Products, Taxon and Taxons fields hold, by code, and what their controls say about
 * them. The kind is also what the search endpoint searches. See "Shipped field types" in docs/spec/extending.md
 */
import type { MessageKey } from '../i18n';
import type { FieldSchema, JsonValue } from '../types';
import { isJsonObject } from '../values';

export interface CatalogKind {
    /** What the search endpoint searches, and the type of reference the field stores: product or taxon. */
    kind: string;
    /** The list control's add button. */
    add: MessageKey;
    /** The search box's name. */
    search: MessageKey;
}

const PRODUCT: CatalogKind = { kind: 'product', add: 'setono_sylius_cms.builder.items.add_product', search: 'setono_sylius_cms.builder.items.search_products' };
const TAXON: CatalogKind = { kind: 'taxon', add: 'setono_sylius_cms.builder.items.add_taxon', search: 'setono_sylius_cms.builder.items.search_taxons' };

/** The kind of item a field holds, by its field type: one item, or a list of them. */
const CATALOG_KINDS: Readonly<Record<string, CatalogKind>> = { product: PRODUCT, products: PRODUCT, taxon: TAXON, taxons: TAXON };

export function catalogKindOf(schema: FieldSchema): CatalogKind {
    return CATALOG_KINDS[schema['x-cms-type']] ?? PRODUCT;
}

/** The most items an editor may pick for a list field, from its schema, or null for no most. */
export function mostOf(schema: FieldSchema): number | null {
    const options = isJsonObject(schema['x-cms-options']) ? schema['x-cms-options'] : {};

    return typeof options.max === 'number' ? options.max : null;
}

/** The codes a hand-picked list holds, in order. Any other value holds none. */
export function codesOf(value: JsonValue | undefined): string[] {
    return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string' && item !== '') : [];
}
