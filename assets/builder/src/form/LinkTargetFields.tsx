import { newBinding, propertyOf, type BindablePropertyDefinition } from '../bindings';
import { useBindableProperties } from '../bindingsContext';
import { useTranslator } from '../i18n';
import { useLinkRef, useLinkTargets } from '../linkTargets';
import type { EditedLink, LinkChange, LinkTargetDefinition } from '../links';
import { SearchPicker } from '../search/SearchPicker';
import { BindingPicker } from './BindingPicker';
import bindingStyles from './Bindings.module.css';
import formStyles from './Form.module.css';
import styles from './LinkControl.module.css';

export interface LinkTargetFieldsProps {
    /** The target select's id, which a label outside names. The other parts take ids from it. */
    id: string;
    /** The link targets offered, in order. */
    offered: readonly LinkTargetDefinition[];
    link: Pick<EditedLink, 'target' | 'ref' | 'anchor' | 'refFallback'> & { binding?: EditedLink['binding'] };
    /** The properties of type url a typed ref can be bound to, as a Link field's can. Left out, it can't be bound, as a link in rich text can't. */
    bindable?: readonly BindablePropertyDefinition[] | undefined;
    onChange: (change: LinkChange) => void;
    required?: boolean | undefined;
    describedBy?: string | undefined;
}

/**
 * Where a link leads: its target, what it links to, picked with the target's search or typed for a URL, and an anchor.
 * The Link field's control and links in rich text share it. See "Links" in docs/spec/content-model.md
 */
export function LinkTargetFields({ id, offered, link, onChange, required, describedBy, bindable }: LinkTargetFieldsProps) {
    const t = useTranslator();
    const linkTargets = useLinkTargets();
    const properties = useBindableProperties();
    const binding = link.binding ?? null;
    const boundLabel = binding === null ? null : (propertyOf(properties, binding.$bind)?.label ?? binding.$bind);
    // A bound link leads to a URL the binding resolves to, so it's a link to a URL
    const canBind = bindable !== undefined && link.target === 'url' && (bindable.length > 0 || binding !== null);
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
                    <div className={bindingStyles.chipRow}>
                        <label htmlFor={`${id}-ref`} className={styles.label}>
                            {binding === null ? t('setono_sylius_cms.builder.link.url') : t('setono_sylius_cms.builder.binding.fallback')}
                        </label>
                        {canBind && (
                            <BindingPicker
                                offered={bindable ?? []}
                                label={t('setono_sylius_cms.builder.binding.bind', { '%field%': t('setono_sylius_cms.builder.link.url') })}
                                className={bindingStyles.labelRowBind}
                                onPick={(property) => onChange({ bind: newBinding(property.binding) })}
                            />
                        )}
                    </div>
                    {binding !== null && boundLabel !== null && (
                        <div className={bindingStyles.chipRow} role="group" aria-label={t('setono_sylius_cms.builder.binding.bound', { '%property%': boundLabel })}>
                            <span className={bindingStyles.chip} data-binding={binding.$bind}>
                                <span aria-hidden="true">⚡</span>
                                {boundLabel}
                            </span>
                            <button type="button" className={bindingStyles.unbind} aria-label={t('setono_sylius_cms.builder.binding.unbind', { '%property%': boundLabel })} onClick={() => onChange({ bind: null })}>
                                ✕
                            </button>
                        </div>
                    )}
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
