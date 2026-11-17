import { useMemo } from 'react';
import { useBuilderSession, useProvidedContext } from '../context';
import { useTranslator } from '../i18n';
import { PickedControl } from './PickedControl';
import type { FieldControlProps } from './types';

/** What the search endpoint searches for a Snippet field. */
export const SNIPPET = 'snippet';

/**
 * A Snippet field: the snippet it holds, by its title, picked from a search of the snippets. It stores the snippet's
 * UUID. The search offers only the snippets whose declared context the edited entry provides, and never the entry
 * itself. See "Snippet references" in docs/spec/builder.md
 */
export function SnippetControl(props: FieldControlProps) {
    const t = useTranslator();

    return <PickedControl {...props} kind={SNIPPET} parameters={useNarrowing()} searchLabel={t('setono_sylius_cms.builder.snippet.search')} />;
}

/**
 * What narrows the search in the builder: the context keys the edited entry provides, as the snippet's context setting
 * changes them, and the entry itself, which a snippet can't show. Outside the builder nothing narrows it
 */
function useNarrowing(): Record<string, string> | undefined {
    const session = useBuilderSession();
    const provided = useProvidedContext();

    return useMemo(
        () => (session === null || provided === null ? undefined : { provides: provided.join(','), entry: session.store.getState().entry.uuid }),
        [session, provided],
    );
}
