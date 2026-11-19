import { useTranslator } from '../i18n';
import { catalogKindOf, catalogParametersOf } from './catalog';
import { PickedControl } from './PickedControl';
import type { FieldControlProps } from './types';

/**
 * A Product, Taxon or Entry field: the product, taxon or entry it holds, picked from a search, of the content type it
 * names for an Entry field. It stores the code or UUID of what's chosen.
 */
export function CatalogControl({ schema, ...props }: FieldControlProps) {
    const t = useTranslator();
    const { kind, search } = catalogKindOf(schema);

    return <PickedControl {...props} kind={kind} searchLabel={t(search)} parameters={catalogParametersOf(schema)} />;
}
