import { useId } from 'react';
import { newCondition } from '../conditions';
import { useBuilder, useBuilderState } from '../context';
import { contextNames, missingContext, providedContext } from '../hostPageContext';
import { useTranslator } from '../i18n';
import type { ConditionKindDefinition, StoredBlock, VisibilityCondition } from '../types';
import { Condition } from './Condition';
import { PlusIcon } from './icons';
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
 * provides the context of. A kind that needs context the entry doesn't provide is named with what it needs, and can't
 * be picked. See "Visibility conditions" in docs/spec/targeting.md
 */
export function VisibilityConditions({ block }: { block: StoredBlock }) {
    const t = useTranslator();
    const headingId = useId();
    const { store } = useBuilder();
    const kinds = useBuilderState((state) => state.conditionKinds);
    const contextKeys = useBuilderState((state) => state.contextKeys);
    const locale = useBuilderState((state) => state.locale);
    const missing = useMissingContext();
    const set = (conditions: VisibilityCondition[]): void => store.getState().setConditions(block.key, conditions);
    const first = kinds.find((kind) => missing(kind).length === 0);
    const unavailable = (kind: ConditionKindDefinition): string | null => {
        const needs = missing(kind);

        return needs.length === 0 ? null : t('setono_sylius_cms.builder.needs_context', { '%context%': contextNames(needs, contextKeys, t).join(', ') });
    };

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
                                kinds={kinds}
                                unavailable={unavailable}
                                locale={locale}
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
