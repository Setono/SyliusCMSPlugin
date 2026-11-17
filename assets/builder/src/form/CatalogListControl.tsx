import type { ReactNode } from 'react';
import { useTranslator } from '../i18n';
import { useKnownRefs } from '../search/context';
import { SearchPicker } from '../search/SearchPicker';
import { offeredSources, sourceOf } from '../sources/sources';
import { ArrowDownIcon, ArrowUpIcon, TrashIcon } from '../shell/icons';
import styles from './CatalogControl.module.css';
import { FilledBy } from './FilledBy';
import { catalogKindOf, codesOf, mostOf, type CatalogKind } from './catalog';
import { CatalogSummary } from './CatalogSummary';
import type { FieldControlProps } from './types';

/**
 * A Products or Taxons field: hand-picked, or filled by a source. When the field offers sources, or a source fills it,
 * the "Filled by" select comes first and takes the field's id, above the hand-picked items or the source's parameters.
 * Otherwise it's only hand-picked. See "List fields" in docs/spec/sources.md
 */
export function CatalogListControl({ id, value, onChange, schema, locale, describedBy }: FieldControlProps) {
    const source = sourceOf(value);
    const handPicked = (itemsId: string, itemsDescribedBy: string | undefined) => (
        <HandPickedList id={itemsId} codes={codesOf(value)} onChange={onChange} catalogKind={catalogKindOf(schema)} max={mostOf(schema)} label={schema['x-cms-label']} describedBy={itemsDescribedBy} />
    );

    if (source === null && offeredSources(schema).length === 0) {
        return handPicked(id, describedBy);
    }

    return (
        <FilledBy id={id} schema={schema} source={source} onChange={onChange} locale={locale} describedBy={describedBy}>
            {handPicked(`${id}-items`, undefined)}
        </FilledBy>
    );
}

export interface HandPickedListProps {
    id: string;
    /** The codes it holds, in the editor's order. */
    codes: readonly string[];
    onChange: (codes: string[]) => void;
    catalogKind: CatalogKind;
    /** The most it holds, or null for no most. */
    max: number | null;
    /** The field's label, which names the list. */
    label: string;
    describedBy?: string | undefined;
}

/**
 * A hand-picked list of products or taxons: what it holds, in the editor's order, each by name, moved up or down or
 * removed, and a button that searches the catalog and adds what's picked to the end, which the field's label names.
 * What it holds already isn't added again, and it holds no more than its most. One that no longer exists stays in the
 * list, flagged, until it's removed.
 */
export function HandPickedList({ id, codes, onChange, catalogKind, max, label, describedBy }: HandPickedListProps) {
    const t = useTranslator();
    const known = useKnownRefs(catalogKind.kind, codes);
    const full = max !== null && codes.length >= max;

    const move = (index: number, by: -1 | 1): void => {
        const next = [...codes];
        const [moved] = next.splice(index, 1);
        if (moved !== undefined) {
            next.splice(index + by, 0, moved);
            onChange(next);
        }
    };

    return (
        <div className={styles.control}>
            {codes.length === 0 ? (
                <p className={styles.nothing}>{t('setono_sylius_cms.builder.items.nothing_picked')}</p>
            ) : (
                <ul className={styles.list} aria-label={label}>
                    {codes.map((code, index) => (
                        <li key={`${code}-${String(index)}`} className={styles.listItem}>
                            <CatalogSummary code={code} item={known[code]} />
                            <span className={styles.itemActions}>
                                <IconButton label={t('setono_sylius_cms.builder.move_up')} disabled={index === 0} onClick={() => move(index, -1)}>
                                    <ArrowUpIcon />
                                </IconButton>
                                <IconButton label={t('setono_sylius_cms.builder.move_down')} disabled={index === codes.length - 1} onClick={() => move(index, 1)}>
                                    <ArrowDownIcon />
                                </IconButton>
                                <IconButton label={t('setono_sylius_cms.builder.items.remove')} onClick={() => onChange(codes.filter((_, at) => at !== index))}>
                                    <TrashIcon />
                                </IconButton>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <div className={styles.actions}>
                <SearchPicker
                    id={id}
                    kind={catalogKind.kind}
                    label={t(catalogKind.add)}
                    searchLabel={t(catalogKind.search)}
                    disabled={full}
                    describedBy={describedBy}
                    onPick={(result) => {
                        if (!codes.includes(result.ref)) {
                            onChange([...codes, result.ref]);
                        }
                    }}
                />
                {max !== null && <span className={styles.most}>{t('setono_sylius_cms.builder.items.at_most', { '%max%': String(max) })}</span>}
            </div>
        </div>
    );
}

function IconButton({ label, disabled = false, onClick, children }: { label: string; disabled?: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button type="button" className={styles.iconButton} aria-label={label} title={label} disabled={disabled} onClick={onClick}>
            {children}
        </button>
    );
}
