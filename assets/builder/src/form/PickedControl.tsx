import { useTranslator } from '../i18n';
import { useKnownRefs } from '../search/context';
import { SearchPicker } from '../search/SearchPicker';
import styles from './Picked.module.css';
import { PickedSummary } from './PickedSummary';
import type { FieldControlProps } from './types';

export type PickedControlProps = Pick<FieldControlProps, 'id' | 'value' | 'onChange' | 'describedBy'> & {
    /** What the search endpoint searches, such as product or snippet. */
    kind: string;
    /** The search's label, translated. */
    searchLabel: string;
    /** What narrows the search, such as the context keys a snippet must fit. */
    parameters?: Readonly<Record<string, string>> | undefined;
};

/**
 * A field that holds one record by its ref, such as a Product, Taxon or Snippet field: what it holds, by name, a button
 * that searches to choose or change it, which the field's label names, and one that removes it. It stores the ref of
 * what's chosen.
 */
export function PickedControl({ id, value, onChange, describedBy, kind, searchLabel, parameters }: PickedControlProps) {
    const t = useTranslator();
    const ref = typeof value === 'string' && value !== '' ? value : null;
    const known = useKnownRefs(kind, ref === null ? [] : [ref]);

    return (
        <div className={styles.picked}>
            {ref === null ? (
                <span className={`${styles.summary} ${styles.nothing}`}>{t('setono_sylius_cms.builder.items.nothing_picked')}</span>
            ) : (
                <PickedSummary code={ref} item={known[ref]} />
            )}
            <SearchPicker
                id={id}
                kind={kind}
                parameters={parameters}
                label={ref === null ? t('setono_sylius_cms.builder.items.choose') : t('setono_sylius_cms.builder.items.change')}
                searchLabel={searchLabel}
                describedBy={describedBy}
                onPick={(result) => onChange(result.ref)}
            />
            {ref !== null && (
                <button type="button" className={styles.button} onClick={() => onChange(null)}>
                    {t('setono_sylius_cms.builder.items.remove')}
                </button>
            )}
        </div>
    );
}
