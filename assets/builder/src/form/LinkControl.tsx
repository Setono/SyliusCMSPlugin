import { useFallbackChain } from '../context';
import { useTranslator } from '../i18n';
import { useLinkRef, useLinkTargets } from '../linkTargets';
import { linkShape, offeredTargets, readLink, writeLink, type LinkChange } from '../links';
import formStyles from './Form.module.css';
import styles from './LinkControl.module.css';
import { LinkTargetFields } from './LinkTargetFields';
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
    const { found } = useLinkRef(link);
    const change = (made: LinkChange): void => onChange(writeLink(value, shape, locale, made, linkTargets, first));

    return (
        <div className={styles.control}>
            <LinkTargetFields id={id} offered={offered} link={link} onChange={change} required={schema['x-cms-required']} describedBy={describedBy} />
            <div className={styles.part}>
                <label htmlFor={`${id}-label`} className={styles.label}>
                    {t('setono_sylius_cms.builder.link.label')}
                </label>
                <input
                    id={`${id}-label`}
                    type="text"
                    className={formStyles.input}
                    value={link.label}
                    placeholder={link.labelFallback ?? found?.name}
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
