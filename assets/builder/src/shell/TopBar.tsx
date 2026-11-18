import * as Popover from '@radix-ui/react-popover';
import { useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { useBuilder, useBuilderState, usePresenceState, usePreviewState, useSettingsState } from '../context';
import { formatDateTime, fromLocalInput } from '../dates';
import { CONTEXT_FIELD, contextNames } from '../hostPageContext';
import { useTranslator, type MessageKey, type Translate } from '../i18n';
import { hasTranslation, localeOf, offeredLocales } from '../locales';
import { isRemovedLayout, layoutOf } from '../layouts';
import { LocalDateTimeInput } from '../form/LocalDateTimeInput';
import { SearchList } from '../search/SearchPicker';
import searchStyles from '../search/SearchPicker.module.css';
import type { Sample, SampleResult, SnippetPreviewState } from '../snippetPreview';
import { locks } from '../store';
import { localeCounts } from '../translationState';
import type { EntryStatus, FieldSchema, Schedule } from '../types';
import { ArrowLeftIcon, ChevronDownIcon, DesktopIcon, MobileIcon, RedoIcon, UndoIcon } from './icons';
import { saveStateText } from './saveStateText';
import styles from './TopBar.module.css';

const statusLabels: Record<EntryStatus, MessageKey> = {
    never_published: 'setono_sylius_cms.builder.never_published',
    published: 'setono_sylius_cms.builder.published',
    unpublished_changes: 'setono_sylius_cms.builder.unpublished_changes',
};

/** The status label of the entry's scheduled publish, the same the entry lists show. */
export function scheduleLabel(t: Translate, schedule: Schedule): string {
    switch (schedule.status) {
        case 'publishes_at_a_time':
            return t('setono_sylius_cms.builder.publishes_at', { '%at%': formatDateTime(schedule.at) });
        case 'scheduled_publish_overdue':
            return t('setono_sylius_cms.builder.scheduled_publish_overdue');
        case 'scheduled_publish_failed':
            return t('setono_sylius_cms.builder.scheduled_publish_failed');
    }
}

export function TopBar() {
    const t = useTranslator();
    const { backUrl, leave, locales } = useBuilder();
    // The title in the edited locale, or the one it falls back to
    const title = useBuilderState((state) => {
        for (const locale of localeOf(locales, state.locale).fallbackChain) {
            const value = state.entry.translations[locale]?.fields.title;
            if (typeof value === 'string' && value !== '') {
                return value;
            }
        }

        return '';
    });
    const status = useBuilderState((state) => state.status);
    const schedule = useBuilderState((state) => state.schedule);

    return (
        <>
            <div className={styles.bar}>
                <a
                    href={backUrl}
                    className={styles.back}
                    onClick={(event) => {
                        // A click with a modifier key opens the link elsewhere, which the browser does
                        if (event.button === 0 && !(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) {
                            event.preventDefault();
                            leave();
                        }
                    }}
                >
                    <ArrowLeftIcon />
                    {t('setono_sylius_cms.builder.back')}
                </a>
                <div className={styles.heading}>
                    <h1 className={styles.title}>{title}</h1>
                    <span className={styles.status} data-status={status}>
                        {t(statusLabels[status])}
                    </span>
                    {schedule !== null && (
                        <span className={styles.status} data-status={schedule.status}>
                            {scheduleLabel(t, schedule)}
                        </span>
                    )}
                </div>
                <LocaleSwitcher />
                <PreviewChannel />
                <ShownTo />
                <PreviewDate />
                <PreviewWidth />
                <UndoRedo />
                <SaveStatus />
                <Editors />
                <Publish />
            </div>
            <SnippetPreview />
        </>
    );
}

/**
 * The one locale the builder edits, of the locales of the entry's channels, each with its untranslated and outdated
 * counts, or that the entry has no translation in it. Switching reloads the preview in that locale. A store with one
 * locale has nothing to switch. See "Completeness and outdated flags" in docs/spec/localization.md
 */
function LocaleSwitcher() {
    const t = useTranslator();
    const { store, locales, channels } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const entry = useBuilderState((state) => state.entry);
    const blockTypes = useBuilderState((state) => state.rules.blockTypes);
    const layouts = useBuilderState((state) => state.layouts);
    const entryChannels = useSettingsState((state) => state.settings.channels);
    const offered = offeredLocales(locales, channels, entryChannels, locale);

    if (offered.length <= 1) {
        return null;
    }

    const schemaOf = (blockType: string, field: string): FieldSchema | undefined => blockTypes.get(blockType)?.fields.find((definition) => definition.name === field)?.schema;
    const label = (code: string, name: string): string => {
        if (!hasTranslation(entry, code)) {
            return `${name} · ${t('setono_sylius_cms.builder.locale_no_translation')}`;
        }

        const { untranslated, outdated } = localeCounts(entry, code, schemaOf, isRemovedLayout(layouts, entry.tree.layout) ? undefined : layoutOf(layouts, entry.tree.layout));
        const counts = [
            ...(untranslated > 0 ? [t('setono_sylius_cms.builder.locale_untranslated', { '%count%': String(untranslated) })] : []),
            ...(outdated > 0 ? [t('setono_sylius_cms.builder.locale_outdated', { '%count%': String(outdated) })] : []),
        ];

        return [name, ...counts].join(' · ');
    };

    return (
        <select className={styles.channel} aria-label={t('setono_sylius_cms.builder.locale')} value={locale} onChange={(event) => store.getState().setLocale(event.currentTarget.value)}>
            {offered.map(({ code, name }) => (
                <option key={code} value={code}>
                    {label(code, name)}
                </option>
            ))}
        </select>
    );
}

/** The channel the preview shows the entry in, one of the entry's channels. */
function PreviewChannel() {
    const t = useTranslator();
    const channels = usePreviewState((state) => state.channels);
    const channel = usePreviewState((state) => state.channel);
    const setChannel = usePreviewState((state) => state.setChannel);

    if (channel === null) {
        return null;
    }

    return (
        <select
            className={styles.channel}
            aria-label={t('setono_sylius_cms.builder.preview_channel')}
            value={channel}
            onChange={(event) => setChannel(event.currentTarget.value)}
        >
            {channels.map(({ code, name }) => (
                <option key={code} value={code}>
                    {name}
                </option>
            ))}
        </select>
    );
}

/**
 * For a snippet, a row of the top bar of its own: the pages it's for, such as "Product page", the placement whose host
 * page the preview shows, and the sample object the page is of, which starts as one inside the placement's page scope.
 * A warning shows when the sample object is outside it, and where the placement loses its capped slot for the shoppers
 * it's shown to, the snippets they see there instead. See "Snippet preview" in docs/spec/builder.md
 */
function SnippetPreview() {
    const t = useTranslator();
    const { placements, snippetPreview } = useBuilder();
    const contextKeys = useBuilderState((state) => state.contextKeys);
    const declared = useBuilderState((state) => state.entry.fields[CONTEXT_FIELD]);
    const placement = usePreviewState((state) => state.placement);
    const setPlacement = usePreviewState((state) => state.setPlacement);
    const setSample = usePreviewState((state) => state.setSample);
    const token = usePreviewState((state) => state.token);
    const [shown, setShown] = useState<SnippetPreviewState | null>(null);

    // What the page shows changes with each token
    useEffect(() => {
        if (snippetPreview === null || token === null) {
            setShown(null);

            return undefined;
        }

        let active = true;
        snippetPreview.state(token).then(
            (state) => active && setShown(state),
            () => active && setShown(null),
        );

        return () => {
            active = false;
        };
    }, [snippetPreview, token]);

    if (placements === null) {
        return null;
    }

    const keys = Array.isArray(declared) ? declared.filter((key): key is string => typeof key === 'string') : [];
    const context = keys.length === 0 ? t('setono_sylius_cms.builder.context_any_page') : contextNames(keys, contextKeys, t).join(' + ');
    const sample = shown?.sample ?? null;

    return (
        <div className={styles.snippetPreview} role="group" aria-label={t('setono_sylius_cms.builder.snippet_preview.label')}>
            <span className={styles.snippetField}>
                <span className={styles.snippetLabel}>{t('setono_sylius_cms.builder.context')}</span>
                <span className={styles.snippetContext} title={t('setono_sylius_cms.builder.context_help')}>
                    {context}
                </span>
            </span>
            {placements.length === 0 ? (
                <span className={styles.snippetLabel}>{t('setono_sylius_cms.builder.snippet_preview.no_placements')}</span>
            ) : (
                <label className={styles.snippetField}>
                    <span className={styles.snippetLabel}>{t('setono_sylius_cms.builder.snippet_preview.placement')}</span>
                    <select className={styles.channel} value={placement ?? ''} onChange={(event) => setPlacement(event.currentTarget.value === '' ? null : event.currentTarget.value)}>
                        {placements.map(({ uuid, label, removed }) => (
                            <option key={uuid} value={uuid} disabled={removed}>
                                {label}
                            </option>
                        ))}
                        <option value="">{t('setono_sylius_cms.builder.snippet_preview.plain_page')}</option>
                    </select>
                </label>
            )}
            {sample !== null && snippetPreview !== null && token !== null && (
                <span className={styles.snippetField}>
                    <span className={styles.snippetLabel} aria-hidden="true">
                        {t('setono_sylius_cms.builder.snippet_preview.sample')}
                    </span>
                    <SamplePicker sample={sample} search={(query) => snippetPreview.samples(token, query)} onPick={setSample} />
                </span>
            )}
            {sample?.matches === false && (
                <p role="alert" className={styles.outsideScope}>
                    {t('setono_sylius_cms.builder.snippet_preview.outside_scope', { '%sample%': sample.name })}
                </p>
            )}
            {shown?.shownInstead != null && (
                <p className={styles.shownInstead} data-shown-instead>
                    {t('setono_sylius_cms.builder.snippet_preview.shown_instead', { '%snippets%': shown.shownInstead.join(', ') })}
                </p>
            )}
        </div>
    );
}

/**
 * The sample object of a snippet's preview, which opens a search of the objects of its context key, each marked when
 * its page is inside the placement's page scope. Picking one reloads the preview on its page.
 */
function SamplePicker({ sample, search, onPick }: { sample: Sample; search: (query: string) => Promise<SampleResult[]>; onPick: (ref: string) => void }) {
    const t = useTranslator();
    const [open, setOpen] = useState(false);
    const label = t('setono_sylius_cms.builder.snippet_preview.sample');
    const inScope = t('setono_sylius_cms.builder.snippet_preview.in_scope');
    // The page scope of what each result found is, by ref
    const found = useRef(new Map<string, boolean | null>());

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <button type="button" className={styles.sample} aria-label={`${label}: ${sample.name}`} title={label}>
                    {sample.name}
                </button>
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Content className={searchStyles.popover} aria-label={label} side="bottom" align="start" sideOffset={4} collisionPadding={8}>
                    <SearchList
                        api={{
                            search: async (_kind, query) => {
                                const results = await search(query);
                                results.forEach(({ ref, matches }) => found.current.set(ref, matches));

                                return results;
                            },
                        }}
                        kind={sample.key}
                        parameters={undefined}
                        searchLabel={t('setono_sylius_cms.builder.snippet_preview.search_samples')}
                        describe={({ ref }) => (found.current.get(ref) === true ? inScope : null)}
                        onPick={({ ref }) => {
                            setOpen(false);
                            onPick(ref);
                        }}
                    />
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}

/**
 * Who the preview shows the page to: guests, customers signed in without a group, or a customer group. Every condition
 * and audience on the page follows it. Picking another reloads the preview. See "Shown to and Date"
 * in docs/spec/targeting.md
 */
function ShownTo() {
    const t = useTranslator();
    const { customerGroups } = useBuilder();
    const shownTo = usePreviewState((state) => state.shownTo);
    const setShownTo = usePreviewState((state) => state.setShownTo);
    const values = [
        { code: '@guest', name: t('setono_sylius_cms.builder.shown_to_guest') },
        { code: '@no_group', name: t('setono_sylius_cms.builder.no_group') },
        ...customerGroups,
    ];

    return (
        <select className={styles.channel} aria-label={t('setono_sylius_cms.builder.shown_to')} value={shownTo} onChange={(event) => setShownTo(event.currentTarget.value)}>
            {values.map(({ code, name }) => (
                <option key={code} value={code}>
                    {name}
                </option>
            ))}
        </select>
    );
}

/**
 * When the preview shows the page: now, or a date and time the editor picks in their own time zone, which date windows
 * follow. It reloads the preview once the editor leaves the input or presses Enter, rather than at every digit typed,
 * and Now goes back to now.
 */
function PreviewDate() {
    const t = useTranslator();
    const date = usePreviewState((state) => state.date);
    const setDate = usePreviewState((state) => state.setDate);
    const now = t('setono_sylius_cms.builder.preview_now');

    return (
        <div className={styles.date}>
            <LocalDateTimeInput className={styles.dateInput} aria-label={t('setono_sylius_cms.builder.preview_date')} value={date} commitOn="blur" onCommit={setDate} />
            <button type="button" className={styles.now} aria-pressed={date === null} title={t('setono_sylius_cms.builder.preview_now_help')} onClick={() => setDate(null)}>
                {now}
            </button>
        </div>
    );
}

function PreviewWidth() {
    const t = useTranslator();
    const width = usePreviewState((state) => state.width);
    const setWidth = usePreviewState((state) => state.setWidth);
    const desktop = t('setono_sylius_cms.builder.desktop');
    const mobile = t('setono_sylius_cms.builder.mobile');

    return (
        <div role="group" className={styles.widths} aria-label={t('setono_sylius_cms.builder.preview_width')}>
            <button type="button" className={styles.width} aria-label={desktop} title={desktop} aria-pressed={width === 'desktop'} onClick={() => setWidth('desktop')}>
                <DesktopIcon />
            </button>
            <button type="button" className={styles.width} aria-label={mobile} title={mobile} aria-pressed={width === 'mobile'} onClick={() => setWidth('mobile')}>
                <MobileIcon />
            </button>
        </div>
    );
}

/** Undo and redo, which work in the browser on the working copy. */
function UndoRedo() {
    const t = useTranslator();
    const { store } = useBuilder();
    const canUndo = useBuilderState((state) => state.history.done.length > 0);
    const canRedo = useBuilderState((state) => state.history.undone.length > 0);
    const undo = t('setono_sylius_cms.builder.undo');
    const redo = t('setono_sylius_cms.builder.redo');

    return (
        <div className={styles.history}>
            <button type="button" className={styles.historyButton} aria-label={undo} title={undo} disabled={!canUndo} onClick={() => store.getState().undo()}>
                <UndoIcon />
            </button>
            <button type="button" className={styles.historyButton} aria-label={redo} title={redo} disabled={!canRedo} onClick={() => store.getState().redo()}>
                <RedoIcon />
            </button>
        </div>
    );
}

function SaveStatus() {
    const t = useTranslator();
    const { reload } = useBuilder();
    const saveState = useBuilderState((state) => state.saveState);

    return (
        <>
            <span role="status" className={styles.saveState} data-state={saveState}>
                {saveStateText(t, saveState)}
            </span>
            {saveState === 'stale' && (
                <button type="button" className={styles.discardButton} onClick={() => void reload()}>
                    {t('setono_sylius_cms.builder.reload')}
                </button>
            )}
        </>
    );
}

/** Who else has the entry open, by their initials, with their names for screen readers and on hover. */
function Editors() {
    const t = useTranslator();
    const editors = usePresenceState((state) => state.editors);

    if (editors.length === 0) {
        return null;
    }

    return (
        <ul className={styles.editors} aria-label={t('setono_sylius_cms.builder.also_editing')}>
            {editors.map(({ name }, i) => (
                <li key={`${i}-${name}`} className={styles.editor} title={name}>
                    <span aria-hidden="true">{initials(name)}</span>
                    <span className={styles.visuallyHidden}>{name}</span>
                </li>
            ))}
        </ul>
    );
}

/** The first letters of the first and the last word of a name, such as JR for Jane Roe. */
export function initials(name: string): string {
    const words = name.trim().split(/\s+/).filter((word) => word !== '');
    const first = words[0]?.[0] ?? '';
    const last = words.length > 1 ? (words.at(-1)?.[0] ?? '') : '';

    return (first + last).toUpperCase();
}

/**
 * Publish, with a menu to schedule the publish instead, and Discard changes when the entry has a draft. While a
 * scheduled publish locks the working copy, Cancel the schedule in their place.
 */
function Publish() {
    const t = useTranslator();
    const { publish, discard } = useBuilder();
    const publishState = useBuilderState((state) => state.publishState);
    const publishRefusal = useBuilderState((state) => state.publishRefusal);
    const discardState = useBuilderState((state) => state.discardState);
    const hasDraft = useBuilderState((state) => state.status === 'unpublished_changes');
    const locked = useBuilderState((state) => locks(state.schedule));
    const publishing = publishState === 'publishing';
    const discarding = discardState === 'discarding';

    if (locked) {
        return <CancelSchedule />;
    }

    return (
        <div className={styles.publish}>
            {publishState === 'failed' && (
                <span role="alert" className={styles.failed}>
                    {t('setono_sylius_cms.builder.publish_failed')}
                    {publishRefusal !== null && `: ${publishRefusal}`}
                </span>
            )}
            {discardState === 'failed' && (
                <span role="alert" className={styles.failed}>
                    {t('setono_sylius_cms.builder.discard_failed')}
                </span>
            )}
            {hasDraft && (
                <button type="button" className={styles.discardButton} disabled={discarding || publishing} onClick={() => void discard()}>
                    {discarding ? t('setono_sylius_cms.builder.discarding') : t('setono_sylius_cms.builder.discard_changes')}
                </button>
            )}
            <div className={styles.split}>
                <button type="button" className={styles.publishButton} disabled={publishing || discarding} onClick={() => void publish()}>
                    {publishing ? t('setono_sylius_cms.builder.publishing') : t('setono_sylius_cms.builder.publish')}
                </button>
                <SchedulePublish disabled={publishing || discarding} />
            </div>
        </div>
    );
}

/**
 * The Publish button's menu, which schedules the publish for a time instead, entered in the editor's own time zone.
 * Once it's scheduled, the working copy is locked. See "Scheduled publishes" in docs/spec/publishing.md
 */
function SchedulePublish({ disabled }: { disabled: boolean }) {
    const t = useTranslator();
    const { schedule } = useBuilder();
    const scheduleState = useBuilderState((state) => state.scheduleState);
    const scheduleRefusal = useBuilderState((state) => state.scheduleRefusal);
    const [open, setOpen] = useState(false);
    const [at, setAt] = useState('');
    const inputId = useId();
    const menu = t('setono_sylius_cms.builder.schedule_menu');
    const scheduling = scheduleState === 'scheduling';
    const utc = fromLocalInput(at);

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        if (utc === null) {
            return;
        }

        void schedule(utc).then((scheduled) => {
            if (scheduled) {
                setOpen(false);
                setAt('');
            }
        });
    };

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <button type="button" className={styles.publishMenuButton} aria-label={menu} title={menu} disabled={disabled}>
                    <ChevronDownIcon />
                </button>
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Content className={styles.schedule} aria-label={t('setono_sylius_cms.builder.schedule_publish')} align="end" sideOffset={4} collisionPadding={8}>
                    <form className={styles.scheduleForm} onSubmit={submit}>
                        <label htmlFor={inputId} className={styles.scheduleLabel}>
                            {t('setono_sylius_cms.builder.schedule_at')}
                        </label>
                        <input id={inputId} type="datetime-local" className={styles.scheduleInput} required value={at} onChange={(event) => setAt(event.currentTarget.value)} />
                        {scheduleState === 'failed' && (
                            <p role="alert" className={styles.failed}>
                                {t('setono_sylius_cms.builder.schedule_failed')}
                                {scheduleRefusal !== null && `: ${scheduleRefusal}`}
                            </p>
                        )}
                        <button type="submit" className={styles.publishButton} disabled={scheduling || utc === null}>
                            {scheduling ? t('setono_sylius_cms.builder.scheduling') : t('setono_sylius_cms.builder.schedule_publish')}
                        </button>
                    </form>
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}

/** In place of Publish while a scheduled publish locks the working copy: Cancel the schedule, which unlocks it. */
function CancelSchedule() {
    const t = useTranslator();
    const { cancelSchedule } = useBuilder();
    const cancelScheduleState = useBuilderState((state) => state.cancelScheduleState);
    const cancelling = cancelScheduleState === 'cancelling';

    return (
        <div className={styles.publish}>
            {cancelScheduleState === 'failed' && (
                <span role="alert" className={styles.failed}>
                    {t('setono_sylius_cms.builder.cancel_schedule_failed')}
                </span>
            )}
            <button type="button" className={styles.discardButton} disabled={cancelling} onClick={() => void cancelSchedule()}>
                {cancelling ? t('setono_sylius_cms.builder.cancelling_schedule') : t('setono_sylius_cms.builder.cancel_schedule')}
            </button>
        </div>
    );
}
