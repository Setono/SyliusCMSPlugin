import { useTranslator } from '../i18n';
import { useLinkRef, useLinkTargets } from '../linkTargets';
import type { EditedLink, LinkChange, LinkTargetDefinition } from '../links';
import { SearchPicker } from '../search/SearchPicker';
import formStyles from './Form.module.css';
import styles from './LinkControl.module.css';

export interface LinkTargetFieldsProps {
    /** The target select's id, which a label outside names. The other parts take ids from it. */
    id: string;
    /** The link targets offered, in order. */
    offered: readonly LinkTargetDefinition[];
    link: Pick<EditedLink, 'target' | 'ref' | 'anchor' | 'refFallback'>;
    onChange: (change: LinkChange) => void;
    required?: boolean | undefined;
    describedBy?: string | undefined;
}

/**
 * Where a link leads: its target, what it links to, picked with the target's search or typed for a URL, and an anchor.
 * The Link field's control and links in rich text share it. See "Links" in docs/spec/content-model.md
 */
export function LinkTargetFields({ id, offered, link, onChange, required, describedBy }: LinkTargetFieldsProps) {
    const t = useTranslator();
    const linkTargets = useLinkTargets();
    const target = linkTargets.find(({ code }) => code === link.target);
    const { refIsTyped, found } = useLinkRef(link);
    const targetLabel = target?.label ?? link.target;

    return (
        <>
            <select
                id={id}
                className={formStyles.select}
                value={link.target}
                aria-required={required || undefined}
                aria-describedby={describedBy}
                onChange={(event) => onChange({ target: event.currentTarget.value })}
            >
                {offered.map(({ code, label }) => (
                    <option key={code} value={code}>
                        {label}
                    </option>
                ))}
                {/* A target the code no longer has, or the field no longer links to, stays shown until another is picked */}
                {!offered.some(({ code }) => code === link.target) && <option value={link.target}>{link.target}</option>}
            </select>
            {refIsTyped ? (
                <div className={styles.part}>
                    <label htmlFor={`${id}-ref`} className={styles.label}>
                        {t('setono_sylius_cms.builder.link.url')}
                    </label>
                    <input
                        id={`${id}-ref`}
                        type="text"
                        inputMode="url"
                        className={formStyles.input}
                        value={link.ref}
                        placeholder={link.refFallback}
                        onChange={(event) => onChange({ ref: event.currentTarget.value })}
                    />
                </div>
            ) : (
                <div className={styles.picked}>
                    <span className={link.ref === '' ? styles.nothing : styles.name}>{link.ref === '' ? t('setono_sylius_cms.builder.link.nothing_picked') : (found?.name ?? link.ref)}</span>
                    <SearchPicker
                        kind={link.target}
                        label={link.ref === '' ? t('setono_sylius_cms.builder.link.choose') : t('setono_sylius_cms.builder.link.change')}
                        searchLabel={t('setono_sylius_cms.builder.link.search', { '%target%': targetLabel })}
                        disabled={target === undefined}
                        onPick={(result) => onChange({ ref: result.ref })}
                    />
                </div>
            )}
            <div className={styles.part}>
                <label htmlFor={`${id}-anchor`} className={styles.label}>
                    {t('setono_sylius_cms.builder.link.anchor')}
                </label>
                <input id={`${id}-anchor`} type="text" className={formStyles.input} value={link.anchor} onChange={(event) => onChange({ anchor: event.currentTarget.value })} />
            </div>
        </>
    );
}
