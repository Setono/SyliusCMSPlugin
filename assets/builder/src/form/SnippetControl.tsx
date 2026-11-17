import { useBuilderSession } from '../context';
import { providedContext } from '../hostPageContext';
import { useTranslator } from '../i18n';
import { useKnownRefs } from '../search/context';
import { SearchPicker } from '../search/SearchPicker';
import { CatalogSummary } from './CatalogSummary';
import styles from './Picked.module.css';
import type { FieldControlProps } from './types';

/** What the search endpoint searches for a Snippet field. */
export const SNIPPET = 'snippet';

/**
 * A Snippet field: the snippet it holds, by its title, a button that searches the snippets to choose or change it,
 * which the field's label names, and one that removes it. It stores the snippet's UUID. The search offers only the
 * snippets whose declared context the edited entry provides, and never the entry itself. See "Snippet references" in
 * docs/spec/builder.md
 */
export function SnippetControl({ id, value, onChange, describedBy }: FieldControlProps) {
    const t = useTranslator();
    const parameters = useNarrowing();
    const uuid = typeof value === 'string' && value !== '' ? value : null;
    const known = useKnownRefs(SNIPPET, uuid === null ? [] : [uuid]);

    return (
        <div className={styles.picked}>
            {uuid === null ? (
                <span className={`${styles.summary} ${styles.nothing}`}>{t('setono_sylius_cms.builder.items.nothing_picked')}</span>
            ) : (
                <CatalogSummary code={uuid} item={known[uuid]} />
            )}
            <SearchPicker
                id={id}
                kind={SNIPPET}
                parameters={parameters}
                label={uuid === null ? t('setono_sylius_cms.builder.items.choose') : t('setono_sylius_cms.builder.items.change')}
                searchLabel={t('setono_sylius_cms.builder.snippet.search')}
                describedBy={describedBy}
                onPick={(result) => onChange(result.ref)}
            />
            {uuid !== null && (
                <button type="button" className={styles.button} onClick={() => onChange(null)}>
                    {t('setono_sylius_cms.builder.items.remove')}
                </button>
            )}
        </div>
    );
}

/**
 * What narrows the search in the builder: the context keys the edited entry provides, and the entry itself, which a
 * snippet can't show. Outside the builder nothing narrows it
 */
function useNarrowing(): Record<string, string> | undefined {
    const session = useBuilderSession();
    if (session === null) {
        return undefined;
    }

    const { rules, entry } = session.store.getState();

    return { provides: providedContext(rules.provides, entry).join(','), entry: entry.uuid };
}
