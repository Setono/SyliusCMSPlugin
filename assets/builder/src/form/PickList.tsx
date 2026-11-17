import type { ReactNode } from 'react';
import { useTranslator } from '../i18n';
import { ArrowDownIcon, ArrowUpIcon, TrashIcon } from '../shell/icons';
import type { FieldSchema, JsonValue } from '../types';
import { isJsonObject } from '../values';
import styles from './Picked.module.css';

export interface PickListProps {
    /** What it holds, in the editor's order: codes or UUIDs. */
    refs: readonly string[];
    onChange: (refs: string[]) => void;
    /** The field's label, which names the list. */
    label: string;
    /** What it says while it holds nothing. */
    nothing: string;
    /** The remove button's name. */
    removeLabel: string;
    /** What the list shows for each ref it holds, such as its thumbnail and name. */
    summary: (ref: string) => ReactNode;
    /** The control that adds to the end of the list, such as a search or the media picker. */
    add: ReactNode;
    /** What it says about the most it holds, or null when there's no most. */
    most: string | null;
}

/**
 * An ordered list of what a field picked: each by its summary, moved up or down or removed, with the control that adds
 * to its end below it. The Products, Taxons and Media list fields' controls are made of it.
 */
export function PickList({ refs, onChange, label, nothing, removeLabel, summary, add, most }: PickListProps) {
    const t = useTranslator();

    const move = (index: number, by: -1 | 1): void => {
        const next = [...refs];
        const [moved] = next.splice(index, 1);
        if (moved !== undefined) {
            next.splice(index + by, 0, moved);
            onChange(next);
        }
    };

    return (
        <div className={styles.control}>
            {refs.length === 0 ? (
                <p className={styles.nothing}>{nothing}</p>
            ) : (
                <ul className={styles.list} aria-label={label}>
                    {refs.map((ref, index) => (
                        <li key={`${ref}-${String(index)}`} className={styles.listItem}>
                            {summary(ref)}
                            <span className={styles.itemActions}>
                                <IconButton label={t('setono_sylius_cms.builder.move_up')} disabled={index === 0} onClick={() => move(index, -1)}>
                                    <ArrowUpIcon />
                                </IconButton>
                                <IconButton label={t('setono_sylius_cms.builder.move_down')} disabled={index === refs.length - 1} onClick={() => move(index, 1)}>
                                    <ArrowDownIcon />
                                </IconButton>
                                <IconButton label={removeLabel} onClick={() => onChange(refs.filter((_, at) => at !== index))}>
                                    <TrashIcon />
                                </IconButton>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <div className={styles.actions}>
                {add}
                {most !== null && <span className={styles.most}>{most}</span>}
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

/** The refs a list holds, in order: its strings that aren't empty. Any other value holds none. */
export function refsOf(value: JsonValue | undefined): string[] {
    return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string' && item !== '') : [];
}

/** The most a list field holds, from its schema, or null for no most. */
export function mostOf(schema: FieldSchema): number | null {
    const options = isJsonObject(schema['x-cms-options']) ? schema['x-cms-options'] : {};

    return typeof options.max === 'number' ? options.max : null;
}
