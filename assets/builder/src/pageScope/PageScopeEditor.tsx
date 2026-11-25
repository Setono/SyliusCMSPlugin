import * as Dialog from '@radix-ui/react-dialog';
import { useMemo, useState } from 'react';
import { newCondition } from '../conditions';
import { contextNames } from '../hostPageContext';
import { createTranslator, TranslatorProvider, useTranslator } from '../i18n';
import { createSearchApi, type SearchApi } from '../search/api';
import { SearchProvider } from '../search/context';
import { TimeZoneProvider } from '../timeZone';
import { Condition } from '../shell/Condition';
import { PlusIcon } from '../shell/icons';
import conditionStyles from '../shell/SelectionPane.module.css';
import type { ConditionKindDefinition, VisibilityCondition } from '../types';
import type { PageScopesConfig, PlacementScopeConfig } from './config';
import styles from './PageScopeEditor.module.css';

export interface PageScopeEditorProps {
    config: PageScopesConfig;
    placement: PlacementScopeConfig;
    /** What pickers search with, made from the config's endpoint when left out. */
    search?: SearchApi;
}

/**
 * Edit page scope of one placement on its snippet's overview page: a button that opens a dialog with the scope's
 * conditions, edited as a block's visibility conditions are, which Save posts to the overview page's form action as
 * JSON. The placement's slot offers only the kinds that read the host page and whose needs it provides. Cancel keeps
 * the scope as it was. See "Placements" in docs/spec/admin.md and "Page scopes" in docs/spec/slots.md
 */
export function PageScopeEditor({ config, placement, search }: PageScopeEditorProps) {
    const translate = useMemo(() => createTranslator(config.strings), [config]);
    const searchApi = useMemo(() => search ?? createSearchApi(config.endpoints.search), [search, config]);

    return (
        <TranslatorProvider value={translate}>
            <TimeZoneProvider value={config.timeZone}>
                <SearchProvider api={searchApi}>
                    <PageScopeDialog config={config} placement={placement} />
                </SearchProvider>
            </TimeZoneProvider>
        </TranslatorProvider>
    );
}

function PageScopeDialog({ config, placement }: Omit<PageScopeEditorProps, 'search'>) {
    const t = useTranslator();
    const [conditions, setConditions] = useState<VisibilityCondition[]>(() => stored(placement.scope));
    const offered = config.conditionKinds.filter((kind) => placement.kinds.includes(kind.code));
    const first = offered[0];
    // A kind the slot doesn't provide the context of, such as one a project's slot stopped providing, says what it needs
    const unavailable = (kind: ConditionKindDefinition): string | null =>
        placement.kinds.includes(kind.code) ? null : t('setono_sylius_cms.builder.needs_context', { '%context%': contextNames(kind.needs ?? [], config.contextKeys, t).join(', ') });

    return (
        <Dialog.Root onOpenChange={(open) => open && setConditions(stored(placement.scope))}>
            <Dialog.Trigger asChild>
                {/* It sits in the Sylius admin's page, so it looks like the admin's buttons */}
                <button type="button" className="ui labeled icon button">
                    <i className="filter icon" />
                    {t('setono_sylius_cms.builder.page_scope.edit')}
                </button>
            </Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className={styles.overlay} />
                <Dialog.Content className={styles.dialog}>
                    <Dialog.Title className={styles.title}>{t('setono_sylius_cms.builder.page_scope.title')}</Dialog.Title>
                    <Dialog.Description className={styles.description}>{t('setono_sylius_cms.builder.page_scope.description', { '%slot%': placement.slot })}</Dialog.Description>
                    <form method="post" action={placement.action} className={styles.form}>
                        <input type="hidden" name="_csrf_token" value={placement.csrfToken} />
                        <input type="hidden" name="scope" value={JSON.stringify(conditions)} />
                        {conditions.length === 0 ? (
                            <p className={conditionStyles.hint}>{t('setono_sylius_cms.builder.page_scope.every_page')}</p>
                        ) : (
                            <ol className={conditionStyles.conditions}>
                                {conditions.map((condition, index) => (
                                    // A condition has no key of its own, and conditions are only added at the end and removed
                                    <li key={index}>
                                        <Condition
                                            id={`cms-page-scope-${index}`}
                                            number={index + 1}
                                            condition={condition}
                                            kinds={config.conditionKinds}
                                            unavailable={unavailable}
                                            unknownHelp="setono_sylius_cms.builder.page_scope.unknown_condition_kind_help"
                                            locale={config.locale}
                                            onChange={(changed) => setConditions(conditions.map((other, i) => (i === index ? changed : other)))}
                                            onRemove={() => setConditions(conditions.filter((_, i) => i !== index))}
                                        />
                                    </li>
                                ))}
                            </ol>
                        )}
                        {first !== undefined && (
                            <button type="button" className={conditionStyles.addCondition} onClick={() => setConditions([...conditions, newCondition(first.code)])}>
                                <PlusIcon />
                                {t('setono_sylius_cms.builder.add_condition')}
                            </button>
                        )}
                        <div className={styles.actions}>
                            <Dialog.Close asChild>
                                <button type="button" className={styles.secondary}>
                                    {t('setono_sylius_cms.builder.page_scope.cancel')}
                                </button>
                            </Dialog.Close>
                            <button type="submit" className={styles.primary}>
                                {t('setono_sylius_cms.builder.page_scope.save')}
                            </button>
                        </div>
                    </form>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

/** The conditions as they're stored, with parameters stored as an empty list read as none. */
function stored(scope: readonly VisibilityCondition[]): VisibilityCondition[] {
    return scope.map((condition) => ({ ...condition, params: Array.isArray(condition.params) ? {} : condition.params }));
}
