import { useTranslator } from '../i18n';
import { useKnownRefs } from '../search/context';
import { SearchPicker } from '../search/SearchPicker';
import styles from './CatalogControl.module.css';
import { catalogKindOf } from './catalog';
import { CatalogSummary } from './CatalogSummary';
import type { FieldControlProps } from './types';

/**
 * A Product or Taxon field: the product or taxon it holds, by name, a button that searches the catalog to choose or
 * change it, which the field's label names, and one that removes it. It stores the code of what's chosen.
 */
export function CatalogControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    const t = useTranslator();
    const { kind, search } = catalogKindOf(schema);
    const code = typeof value === 'string' && value !== '' ? value : null;
    const known = useKnownRefs(kind, code === null ? [] : [code]);

    return (
        <div className={styles.picked}>
            {code === null ? (
                <span className={`${styles.summary} ${styles.nothing}`}>{t('setono_sylius_cms.builder.items.nothing_picked')}</span>
            ) : (
                <CatalogSummary code={code} item={known[code]} />
            )}
            <SearchPicker
                id={id}
                kind={kind}
                label={code === null ? t('setono_sylius_cms.builder.items.choose') : t('setono_sylius_cms.builder.items.change')}
                searchLabel={t(search)}
                describedBy={describedBy}
                onPick={(result) => onChange(result.ref)}
            />
            {code !== null && (
                <button type="button" className={styles.button} onClick={() => onChange(null)}>
                    {t('setono_sylius_cms.builder.items.remove')}
                </button>
            )}
        </div>
    );
}
