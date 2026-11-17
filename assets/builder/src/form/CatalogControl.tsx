import { useTranslator } from '../i18n';
import { catalogKindOf } from './catalog';
import { PickedControl } from './PickedControl';
import type { FieldControlProps } from './types';

/**
 * A Product or Taxon field: the product or taxon it holds, picked from a search of the catalog. It stores the code of
 * what's chosen.
 */
export function CatalogControl({ schema, ...props }: FieldControlProps) {
    const t = useTranslator();
    const { kind, search } = catalogKindOf(schema);

    return <PickedControl {...props} kind={kind} searchLabel={t(search)} />;
}
