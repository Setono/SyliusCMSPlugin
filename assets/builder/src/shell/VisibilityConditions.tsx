import { useId } from 'react';
import { newCondition, withParam } from '../conditions';
import { useBuilder, useBuilderState } from '../context';
import { FieldRow } from '../form/FieldRow';
import formStyles from '../form/Form.module.css';
import { contextNames, missingContext, providedContext } from '../hostPageContext';
import { useTranslator } from '../i18n';
import type { ConditionKindDefinition, StoredBlock, VisibilityCondition } from '../types';
import { PlusIcon, TrashIcon } from './icons';
import styles from './SelectionPane.module.css';

/**
 * The context keys a condition kind needs that the entry doesn't provide or the snippet doesn't declare, none when it's
 * offered. See "Snippets and their context" in docs/spec/slots.md
 */
function useMissingContext(): (kind: ConditionKindDefinition) => string[] {
    const rules = useBuilderState((state) => state.rules);
    const entry = useBuilderState((state) => state.entry);
    const provided = providedContext(rules.provides, entry);

    return (kind) => missingContext(kind.needs, provided);
}

/**
 * The selected block's visibility conditions, which all have to match for it to show: each one's kind, "is one of" or
 * "is not one of", and its parameters through their fields. A new condition starts with the first kind the entry
 * provides the context of. See "Visibility conditions" in docs/spec/targeting.md
 */
export function VisibilityConditions({ block }: { block: StoredBlock }) {
    const t = useTranslator();
    const headingId = useId();
    const { store } = useBuilder();
    const kinds = useBuilderState((state) => state.conditionKinds);
    const missing = useMissingContext();
    const set = (conditions: VisibilityCondition[]): void => store.getState().setConditions(block.key, conditions);
    const first = kinds.find((kind) => missing(kind).length === 0);

    return (
        <section className={styles.children} aria-labelledby={headingId}>
            <h3 id={headingId} className={styles.subheading}>
                {t('setono_sylius_cms.builder.visibility')}
            </h3>
            {block.conditions.length === 0 ? (
                <p className={styles.hint}>{t('setono_sylius_cms.builder.visibility_help')}</p>
            ) : (
                <ol className={styles.conditions}>
                    {block.conditions.map((condition, index) => (
                        // A condition has no key of its own, and conditions are only added at the end and removed
                        <li key={index}>
                            <Condition
                                id={`cms-condition-${block.key}-${index}`}
                                number={index + 1}
                                condition={condition}
                                onChange={(changed) => set(block.conditions.map((other, i) => (i === index ? changed : other)))}
                                onRemove={() => set(block.conditions.filter((_, i) => i !== index))}
                            />
                        </li>
                    ))}
                </ol>
            )}
            {first !== undefined && (
                <button type="button" className={styles.addCondition} onClick={() => set([...block.conditions, newCondition(first.code)])}>
                    <PlusIcon />
                    {t('setono_sylius_cms.builder.add_condition')}
                </button>
            )}
        </section>
    );
}

interface ConditionProps {
    id: string;
    number: number;
    condition: VisibilityCondition;
    onChange: (condition: VisibilityCondition) => void;
    onRemove: () => void;
}

/**
 * One condition: its kind, whether it's negated and its parameters. Picking another kind starts its parameters anew. A
 * kind that needs context the entry doesn't provide is named with what it needs, and can't be picked.
 */
function Condition({ id, number, condition, onChange, onRemove }: ConditionProps) {
    const t = useTranslator();
    const kinds = useBuilderState((state) => state.conditionKinds);
    const contextKeys = useBuilderState((state) => state.contextKeys);
    const missing = useMissingContext();
    const locale = useBuilderState((state) => state.locale);
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
                        const needs = missing(offered);

                        return (
                            <option key={offered.code} value={offered.code} disabled={needs.length > 0}>
                                {needs.length === 0
                                    ? offered.label
                                    : `${offered.label} (${t('setono_sylius_cms.builder.needs_context', { '%context%': contextNames(needs, contextKeys, t).join(', ') })})`}
                            </option>
                        );
                    })}
                </select>
                <button type="button" className={styles.action} aria-label={remove} title={remove} onClick={onRemove}>
                    <TrashIcon />
                </button>
            </div>
            {kind === undefined ? (
                <p className={styles.unknown}>{t('setono_sylius_cms.builder.unknown_condition_kind_help')}</p>
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
