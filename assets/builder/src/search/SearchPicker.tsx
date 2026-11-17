import * as Popover from '@radix-ui/react-popover';
import { useEffect, useId, useState, type KeyboardEvent } from 'react';
import { useTranslator } from '../i18n';
import type { SearchApi, SearchResult } from './api';
import { useSearch } from './context';
import styles from './SearchPicker.module.css';

export interface SearchPickerProps {
    /** What it searches: product, taxon, entry, or a link target's code. */
    kind: string;
    /** The button's text, such as "Choose…". */
    label: string;
    /** The search box's name, which names the popover too. */
    searchLabel: string;
    disabled?: boolean | undefined;
    describedBy?: string | undefined;
    onPick: (result: SearchResult) => void;
}

/**
 * A button that opens a search of one kind of thing in a popover, such as products, and picks one of what it finds. It
 * searches as the editor types, from the first page of everything, and the arrow keys and Enter pick too. Products
 * and taxons pickers reuse it. Without a search, it's disabled.
 */
export function SearchPicker({ kind, label, searchLabel, disabled = false, describedBy, onPick }: SearchPickerProps) {
    const search = useSearch();
    const [open, setOpen] = useState(false);

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <button type="button" className={styles.button} disabled={disabled || search === null} aria-describedby={describedBy}>
                    {label}
                </button>
            </Popover.Trigger>
            {search !== null && (
                <Popover.Portal>
                    <Popover.Content className={styles.popover} aria-label={searchLabel} side="bottom" align="start" sideOffset={4} collisionPadding={8}>
                        <SearchList
                            api={search.api}
                            kind={kind}
                            searchLabel={searchLabel}
                            onPick={(result) => {
                                search.known.getState().remember(kind, [result]);
                                setOpen(false);
                                onPick(result);
                            }}
                        />
                    </Popover.Content>
                </Popover.Portal>
            )}
        </Popover.Root>
    );
}

type Found = { state: 'searching' } | { state: 'found'; results: SearchResult[] } | { state: 'failed' };

/** How long it waits after a key press before it searches. */
const DELAY = 200;

function SearchList({ api, kind, searchLabel, onPick }: { api: SearchApi; kind: string; searchLabel: string; onPick: (result: SearchResult) => void }) {
    const t = useTranslator();
    const listId = useId();
    const [query, setQuery] = useState('');
    const [found, setFound] = useState<Found>({ state: 'searching' });
    const [active, setActive] = useState(0);
    const results = found.state === 'found' ? found.results : [];

    useEffect(() => {
        let current = true;
        const timer = setTimeout(
            () => {
                api.search(kind, query).then(
                    (answer) => {
                        if (current) {
                            setFound({ state: 'found', results: answer });
                            setActive(0);
                        }
                    },
                    () => current && setFound({ state: 'failed' }),
                );
            },
            query === '' ? 0 : DELAY,
        );

        return () => {
            current = false;
            clearTimeout(timer);
        };
    }, [api, kind, query]);

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive((index) => (results.length === 0 ? 0 : (index + step + results.length) % results.length));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const picked = results[active];
            if (picked !== undefined) {
                onPick(picked);
            }
        }
    };

    return (
        <div className={styles.body}>
            <input
                type="search"
                role="combobox"
                className={styles.search}
                aria-label={searchLabel}
                aria-expanded={results.length > 0}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={results[active] === undefined ? undefined : `${listId}-${active}`}
                placeholder={searchLabel}
                value={query}
                onChange={(event) => {
                    setQuery(event.currentTarget.value);
                    setFound({ state: 'searching' });
                }}
                onKeyDown={onKeyDown}
            />
            {found.state === 'searching' && (
                <p role="status" className={styles.note}>
                    {t('setono_sylius_cms.builder.search.searching')}
                </p>
            )}
            {found.state === 'failed' && (
                <p role="alert" className={styles.note}>
                    {t('setono_sylius_cms.builder.search.failed')}
                </p>
            )}
            {found.state === 'found' && results.length === 0 && <p className={styles.note}>{t('setono_sylius_cms.builder.search.no_results')}</p>}
            <ul id={listId} role="listbox" aria-label={searchLabel} className={styles.list} hidden={results.length === 0}>
                {results.map((result, index) => (
                    <li
                        key={result.ref}
                        id={`${listId}-${index}`}
                        role="option"
                        aria-selected={index === active}
                        className={styles.option}
                        onMouseMove={() => setActive(index)}
                        onClick={() => onPick(result)}
                    >
                        {result.thumbnail === null ? <span className={styles.thumbnail} aria-hidden="true" /> : <img className={styles.thumbnail} src={result.thumbnail} alt="" />}
                        <span className={styles.name}>{result.name}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
