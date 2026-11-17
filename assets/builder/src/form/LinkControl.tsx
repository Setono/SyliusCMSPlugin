import { useFallbackChain } from '../context';
import { useTranslator } from '../i18n';
import { useLinkTargets } from '../linkTargets';
import { linkShape, offeredTargets, readLink, writeLink, type LinkChange } from '../links';
import { useKnownRef } from '../search/context';
import { SearchPicker } from '../search/SearchPicker';
import formStyles from './Form.module.css';
import styles from './LinkControl.module.css';
import type { FieldControlProps } from './types';

/**
 * A Link field: what it links to, picked with the search of its target, or typed for a URL in each locale, an anchor,
 * and a label in each locale, which shows the name of what it links to when it's empty. The field's label names the
 * target select. See "Links" in docs/spec/content-model.md
 */
export function LinkControl({ id, value, onChange, schema, locale, describedBy }: FieldControlProps) {
    const t = useTranslator();
    const linkTargets = useLinkTargets();
    const chain = useFallbackChain(locale);
    const shape = linkShape(schema);
    const offered = offeredTargets(schema, linkTargets);
    const first = offered[0]?.code ?? '';
    const link = readLink(value, shape, chain, first);
    const target = linkTargets.find(({ code }) => code === link.target);
    const typed = target?.translatableRef ?? false;
    const known = useKnownRef(link.target, typed || link.ref === '' ? null : link.ref);
    const change = (made: LinkChange): void => onChange(writeLink(value, shape, locale, made, linkTargets, first));
    const targetLabel = target?.label ?? link.target;

    return (
        <div className={styles.control}>
            <select
                id={id}
                className={formStyles.select}
                value={link.target}
                aria-required={schema['x-cms-required'] || undefined}
                aria-describedby={describedBy}
                onChange={(event) => change({ target: event.currentTarget.value })}
            >
                {offered.map(({ code, label }) => (
                    <option key={code} value={code}>
                        {label}
                    </option>
                ))}
                {/* A target the code no longer has, or the field no longer links to, stays shown until another is picked */}
                {!offered.some(({ code }) => code === link.target) && <option value={link.target}>{link.target}</option>}
            </select>
            {typed ? (
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
                        onChange={(event) => change({ ref: event.currentTarget.value })}
                    />
                </div>
            ) : (
                <div className={styles.picked}>
                    <span className={link.ref === '' ? styles.nothing : styles.name}>{link.ref === '' ? t('setono_sylius_cms.builder.link.nothing_picked') : (known?.name ?? link.ref)}</span>
                    <SearchPicker
                        kind={link.target}
                        label={link.ref === '' ? t('setono_sylius_cms.builder.link.choose') : t('setono_sylius_cms.builder.link.change')}
                        searchLabel={t('setono_sylius_cms.builder.link.search', { '%target%': targetLabel })}
                        disabled={target === undefined}
                        onPick={(result) => change({ ref: result.ref })}
                    />
                </div>
            )}
            <div className={styles.part}>
                <label htmlFor={`${id}-anchor`} className={styles.label}>
                    {t('setono_sylius_cms.builder.link.anchor')}
                </label>
                <input id={`${id}-anchor`} type="text" className={formStyles.input} value={link.anchor} onChange={(event) => change({ anchor: event.currentTarget.value })} />
            </div>
            <div className={styles.part}>
                <label htmlFor={`${id}-label`} className={styles.label}>
                    {t('setono_sylius_cms.builder.link.label')}
                </label>
                <input
                    id={`${id}-label`}
                    type="text"
                    className={formStyles.input}
                    value={link.label}
                    placeholder={link.labelFallback ?? known?.name}
                    aria-describedby={`${id}-label-help`}
                    onChange={(event) => change({ label: event.currentTarget.value })}
                />
                <p id={`${id}-label-help`} className={formStyles.help}>
                    {t('setono_sylius_cms.builder.link.label_help')}
                </p>
            </div>
        </div>
    );
}
