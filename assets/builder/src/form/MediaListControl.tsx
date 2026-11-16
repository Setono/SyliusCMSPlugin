import type { ReactNode } from 'react';
import { useTranslator } from '../i18n';
import { useKnownMediaItems, useMediaPicker } from '../media/picker';
import { ArrowDownIcon, ArrowUpIcon, TrashIcon } from '../shell/icons';
import type { JsonValue } from '../types';
import styles from './MediaControl.module.css';
import { MediaItemSummary, mediaOptions } from './MediaItemSummary';
import type { FieldControlProps } from './types';

/**
 * A Media list field: the media items it holds, in order, each moved up or down or removed, and a button that opens the
 * media library as a picker of several media items of the kinds the field takes, which the field's label names. Media
 * lists are always hand-picked. What's picked joins the end of the list, without those it holds already, up to its most.
 * A media item that was deleted stays in the list, flagged, until it's removed. See "Media library" in docs/spec/admin.md
 */
export function MediaListControl({ id, value, onChange, schema, describedBy }: FieldControlProps) {
    const t = useTranslator();
    const picker = useMediaPicker();
    const uuids = uuidsOf(value);
    const known = useKnownMediaItems(uuids);
    const { kinds, max } = mediaOptions(schema);
    const full = max !== null && uuids.length >= max;
    const label = schema['x-cms-label'];

    const pick = async (): Promise<void> => {
        const picked = await picker?.pickMedia({ kinds, multiple: true });
        if (picked === null || picked === undefined) {
            return;
        }

        const added = picked.map(({ uuid }) => uuid).filter((uuid, index, all) => !uuids.includes(uuid) && all.indexOf(uuid) === index);
        const next = [...uuids, ...added];
        onChange(max === null ? next : next.slice(0, max));
    };

    const move = (index: number, by: -1 | 1): void => {
        const next = [...uuids];
        const [moved] = next.splice(index, 1);
        if (moved !== undefined) {
            next.splice(index + by, 0, moved);
            onChange(next);
        }
    };

    return (
        <div className={styles.control}>
            {uuids.length === 0 ? (
                <p className={styles.nothing}>{t('setono_sylius_cms.builder.media.nothing_picked')}</p>
            ) : (
                <ul className={styles.list} aria-label={label}>
                    {uuids.map((uuid, index) => (
                        <li key={`${uuid}-${String(index)}`} className={styles.listItem}>
                            <MediaItemSummary item={known[uuid]} />
                            <span className={styles.itemActions}>
                                <IconButton label={t('setono_sylius_cms.builder.move_up')} disabled={index === 0} onClick={() => move(index, -1)}>
                                    <ArrowUpIcon />
                                </IconButton>
                                <IconButton label={t('setono_sylius_cms.builder.move_down')} disabled={index === uuids.length - 1} onClick={() => move(index, 1)}>
                                    <ArrowDownIcon />
                                </IconButton>
                                <IconButton label={t('setono_sylius_cms.builder.media.remove')} onClick={() => onChange(uuids.filter((_, at) => at !== index))}>
                                    <TrashIcon />
                                </IconButton>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <div className={styles.actions}>
                <button id={id} type="button" className={styles.button} aria-describedby={describedBy} disabled={picker === null || full} onClick={() => void pick()}>
                    {t('setono_sylius_cms.builder.media.add')}
                </button>
                {max !== null && <span className={styles.most}>{t('setono_sylius_cms.builder.media.at_most', { '%max%': String(max) })}</span>}
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

function uuidsOf(value: JsonValue | undefined): string[] {
    return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];
}
