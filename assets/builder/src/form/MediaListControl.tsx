import { useTranslator } from '../i18n';
import { useKnownMediaItems, useMediaPicker } from '../media/picker';
import { MediaItemSummary, mediaOptions } from './MediaItemSummary';
import styles from './Picked.module.css';
import { PickList, refsOf } from './PickList';
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
    const uuids = refsOf(value);
    const known = useKnownMediaItems(uuids);
    const { kinds, max } = mediaOptions(schema);

    const pick = async (): Promise<void> => {
        const picked = await picker?.pickMedia({ kinds, multiple: true });
        if (picked === null || picked === undefined) {
            return;
        }

        const added = picked.map(({ uuid }) => uuid).filter((uuid, index, all) => !uuids.includes(uuid) && all.indexOf(uuid) === index);
        const next = [...uuids, ...added];
        onChange(max === null ? next : next.slice(0, max));
    };

    return (
        <PickList
            refs={uuids}
            onChange={onChange}
            label={schema['x-cms-label']}
            nothing={t('setono_sylius_cms.builder.media.nothing_picked')}
            removeLabel={t('setono_sylius_cms.builder.media.remove')}
            summary={(uuid) => <MediaItemSummary item={known[uuid]} />}
            most={max === null ? null : t('setono_sylius_cms.builder.media.at_most', { '%max%': String(max) })}
            add={
                <button
                    id={id}
                    type="button"
                    className={styles.button}
                    aria-describedby={describedBy}
                    disabled={picker === null || (max !== null && uuids.length >= max)}
                    onClick={() => void pick()}
                >
                    {t('setono_sylius_cms.builder.media.add')}
                </button>
            }
        />
    );
}
