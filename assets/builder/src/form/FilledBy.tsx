import { useEffect, useState, type ReactNode } from 'react';
import { missingContext } from '../hostPageContext';
import { useTranslator } from '../i18n';
import { useSourceCount, useSources } from '../sources/context';
import { filledBy, fitsEntry, limitOf, offeredSources, paramSchema, withLimit, withSourceParam, type SourceDefinition, type SourceValue } from '../sources/sources';
import type { FieldSchema, JsonValue } from '../types';
import { FieldRow } from './FieldRow';
import styles from './FilledBy.module.css';
import formStyles from './Form.module.css';

export interface FilledByProps {
    /** The select's id, which the field's label points at too. */
    id: string;
    schema: FieldSchema;
    /** The source that fills the list, or null while it's hand-picked. */
    source: SourceValue | null;
    onChange: (value: JsonValue | null) => void;
    locale: string;
    describedBy?: string | undefined;
    /** What shows while it's hand-picked: the hand-picked items. */
    children: ReactNode;
}

/**
 * How a list field is filled: Hand-picked, first, or one of the sources the field offers whose needed context the entry
 * provides or the snippet declares, and that fits the entry's content type, as the "Filled by" select picks it. A source's parameters are filled in inline, the number of items to show sits beside the select, starting at
 * the field's limit, and how many items the source yields right now in the preview shows next to "Filled by". A source
 * the registry no longer gives, disabled or removed, stays picked and flagged until another way is picked. See
 * "Sources" in docs/spec/builder.md
 */
export function FilledBy({ id, schema, source, onChange, locale, describedBy, children }: FilledByProps) {
    const t = useTranslator();
    const { sources, provided, entryContentType, categoryTypes = {} } = useSources();
    const offered = offeredSources(schema)
        .map((code) => sources.find((definition) => definition.code === code))
        .filter((definition): definition is SourceDefinition => definition !== undefined)
        .filter((definition) => provided === undefined || missingContext(definition.needs, provided).length === 0)
        .filter((definition) => fitsEntry(definition, entryContentType));
    const current = source === null ? undefined : sources.find(({ code }) => code === source.$source);

    const pick = (code: string): void => {
        // A source starts afresh, with no parameters, and keeps the number to show of the one it replaces
        onChange(code === '' ? [] : filledBy(code, source?.$limit ?? limitOf(schema)));
    };

    return (
        <div className={styles.control}>
            <div className={styles.filledByHeader}>
                <label htmlFor={id} className={styles.filledByLabel}>
                    {t('setono_sylius_cms.builder.sources.filled_by')}
                </label>
                {source !== null && current !== undefined && <SourceCount source={source} />}
            </div>
            <div className={styles.filledByRow}>
                <select id={id} className={formStyles.select} value={source?.$source ?? ''} aria-describedby={describedBy} onChange={(event) => pick(event.currentTarget.value)}>
                    <option value="">{t('setono_sylius_cms.builder.sources.hand_picked')}</option>
                    {source !== null && !offered.some(({ code }) => code === source.$source) && (
                        <option value={source.$source}>{current?.label ?? t('setono_sylius_cms.builder.sources.unavailable', { '%source%': source.$source })}</option>
                    )}
                    {offered.map(({ code, label }) => (
                        <option key={code} value={code}>
                            {label}
                        </option>
                    ))}
                </select>
                {source !== null && <LimitInput id={`${id}-limit`} limit={source.$limit} onChange={(limit) => onChange(withLimit(source, limit))} />}
            </div>
            {source === null && children}
            {source !== null && current === undefined && <p className={styles.unavailable}>{t('setono_sylius_cms.builder.sources.unavailable_help')}</p>}
            {source !== null &&
                current?.fields.map(({ name, schema: declared }) => {
                    // From a collection's categories are the chosen collection's, and left out until one with categories is chosen
                    const shown = paramSchema(declared, source.$params, categoryTypes);

                    return shown === null ? null : (
                        <FieldRow
                            key={`${source.$source}-${name}`}
                            id={`${id}-${name}`}
                            schema={shown}
                            locale={locale}
                            value={source.$params[name]}
                            onChange={(value) => onChange(withSourceParam(source, name, value, current.fields))}
                            languageBadge={false}
                        />
                    );
                })}
        </div>
    );
}

/** How many items the source yields right now, in the preview, once it's counted. */
function SourceCount({ source }: { source: SourceValue }) {
    const t = useTranslator();
    const count = useSourceCount(source.$source, source.$params);

    return (
        <span className={styles.count} aria-live="polite">
            {count === null ? null : count === 1 ? t('setono_sylius_cms.builder.sources.count_one') : t('setono_sylius_cms.builder.sources.count', { '%count%': String(count) })}
        </span>
    );
}

/**
 * The number of items to show. What's typed is kept as it is while it's no whole number of at least one, and only a
 * number of at least one is stored; leaving the input shows the stored number again.
 */
function LimitInput({ id, limit, onChange }: { id: string; limit: number; onChange: (limit: number) => void }) {
    const t = useTranslator();
    const [typed, setTyped] = useState(String(limit));

    useEffect(() => {
        setTyped(String(limit));
    }, [limit]);

    return (
        <span className={styles.limit}>
            <label htmlFor={id}>{t('setono_sylius_cms.builder.sources.limit')}</label>
            <input
                id={id}
                className={`${formStyles.input} ${styles.limitInput}`}
                type="number"
                min={1}
                step={1}
                value={typed}
                onChange={(event) => {
                    const value = event.currentTarget.value;
                    setTyped(value);
                    const parsed = Number(value);
                    if (value !== '' && Number.isInteger(parsed) && parsed >= 1 && parsed !== limit) {
                        onChange(parsed);
                    }
                }}
                onBlur={() => setTyped(String(limit))}
            />
        </span>
    );
}
