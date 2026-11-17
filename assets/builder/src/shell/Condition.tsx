import { newCondition, withParam } from '../conditions';
import { FieldRow } from '../form/FieldRow';
import formStyles from '../form/Form.module.css';
import { useTranslator, type MessageKey } from '../i18n';
import type { ConditionKindDefinition, VisibilityCondition } from '../types';
import { TrashIcon } from './icons';
import styles from './SelectionPane.module.css';

export interface ConditionProps {
    id: string;
    number: number;
    condition: VisibilityCondition;
    /** The kinds the condition can be of, in the order they're offered. */
    kinds: readonly ConditionKindDefinition[];
    /** Why a kind can't be picked here, such as "Needs: Product page", or null when it can. */
    unavailable?: (kind: ConditionKindDefinition) => string | null;
    /** What a condition of a kind the code no longer has says it does. */
    unknownHelp?: MessageKey;
    /** The edited locale. Conditions' parameters are shared by all translations. */
    locale: string;
    onChange: (condition: VisibilityCondition) => void;
    onRemove: () => void;
}

/**
 * One condition, a block's visibility condition or one of a placement's page scope: its kind, whether it's negated and
 * its parameters. Picking another kind starts its parameters anew. A kind that can't be picked here is named with why.
 * See "Visibility conditions" in docs/spec/targeting.md and "Page scopes" in docs/spec/slots.md
 */
export function Condition({ id, number, condition, kinds, unavailable = () => null, unknownHelp = 'setono_sylius_cms.builder.unknown_condition_kind_help', locale, onChange, onRemove }: ConditionProps) {
    const t = useTranslator();
    const kind = kinds.find(({ code }) => code === condition.kind);
    // As the kind words it, such as a date window's "is inside", or else "is one of"
    const is = kind?.matches ?? t('setono_sylius_cms.builder.is_one_of');
    const isNot = kind?.matchesNot ?? t('setono_sylius_cms.builder.is_not_one_of');
    const remove = t('setono_sylius_cms.builder.remove_condition');

    return (
        <div role="group" aria-label={t('setono_sylius_cms.builder.condition', { '%number%': String(number) })} className={styles.condition}>
            <div className={styles.conditionHeader}>
                <label htmlFor={`${id}-kind`} className={styles.visuallyHidden}>
                    {t('setono_sylius_cms.builder.condition_kind')}
                </label>
                <select id={`${id}-kind`} className={formStyles.select} value={condition.kind} onChange={(event) => onChange(newCondition(event.currentTarget.value))}>
                    {kind === undefined && <option value={condition.kind}>{t('setono_sylius_cms.builder.unknown_condition_kind', { '%kind%': condition.kind })}</option>}
                    {kinds.map((offered) => {
                        const reason = unavailable(offered);

                        return (
                            <option key={offered.code} value={offered.code} disabled={reason !== null}>
                                {reason === null ? offered.label : `${offered.label} (${reason})`}
                            </option>
                        );
                    })}
                </select>
                <button type="button" className={styles.action} aria-label={remove} title={remove} onClick={onRemove}>
                    <TrashIcon />
                </button>
            </div>
            {kind === undefined ? (
                <p className={styles.unknown}>{t(unknownHelp)}</p>
            ) : (
                <>
                    <label htmlFor={`${id}-negate`} className={styles.visuallyHidden}>
                        {t('setono_sylius_cms.builder.condition_match')}
                    </label>
                    <select
                        id={`${id}-negate`}
                        className={formStyles.select}
                        value={condition.negate ? 'not' : 'is'}
                        onChange={(event) => onChange({ ...condition, negate: event.currentTarget.value === 'not' })}
                    >
                        <option value="is">{is}</option>
                        <option value="not">{isNot}</option>
                    </select>
                    {kind.fields.map(({ name, schema }) => (
                        <FieldRow
                            key={name}
                            id={`${id}-${name}`}
                            schema={schema}
                            locale={locale}
                            value={condition.params[name]}
                            onChange={(value) => onChange(withParam(condition, name, value))}
                            languageBadge={false}
                        />
                    ))}
                </>
            )}
        </div>
    );
}
