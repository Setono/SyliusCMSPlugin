import * as Popover from '@radix-ui/react-popover';
import { useId, useState, type FormEvent } from 'react';
import { useBuilder, useBuilderState, usePresenceState, usePreviewState, useSettingsState } from '../context';
import { formatDateTime, fromLocalInput } from '../dates';
import { useTranslator, type MessageKey, type Translate } from '../i18n';
import { hasTranslation, localeOf, offeredLocales } from '../locales';
import { isRemovedLayout, layoutOf } from '../layouts';
import { LocalDateTimeInput } from '../form/LocalDateTimeInput';
import { locks } from '../store';
import { localeCounts } from '../translationState';
import type { EntryStatus, Schedule } from '../types';
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

    const isTranslatable = (blockType: string, field: string): boolean =>
        blockTypes.get(blockType)?.fields.find((definition) => definition.name === field)?.schema['x-cms-translatable'] === true;
    const label = (code: string, name: string): string => {
        if (!hasTranslation(entry, code)) {
            return `${name} · ${t('setono_sylius_cms.builder.locale_no_translation')}`;
        }

        const { untranslated, outdated } = localeCounts(entry, code, isTranslatable, isRemovedLayout(layouts, entry.tree.layout) ? undefined : layoutOf(layouts, entry.tree.layout));
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
