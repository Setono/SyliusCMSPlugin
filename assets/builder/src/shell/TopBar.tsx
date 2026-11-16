import { useBuilder, useBuilderState, usePresenceState, usePreviewState, useSettingsState } from '../context';
import { useTranslator, type MessageKey } from '../i18n';
import { hasTranslation, localeOf, offeredLocales } from '../locales';
import { localeCounts } from '../translationState';
import type { EntryStatus } from '../types';
import { ArrowLeftIcon, DesktopIcon, MobileIcon, RedoIcon, UndoIcon } from './icons';
import { saveStateText } from './saveStateText';
import styles from './TopBar.module.css';

const statusLabels: Record<EntryStatus, MessageKey> = {
    never_published: 'setono_sylius_cms.builder.never_published',
    published: 'setono_sylius_cms.builder.published',
    unpublished_changes: 'setono_sylius_cms.builder.unpublished_changes',
};

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
            </div>
            <LocaleSwitcher />
            <PreviewChannel />
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

        const { untranslated, outdated } = localeCounts(entry, code, isTranslatable);
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

/** Publish, and Discard changes when the entry has a draft. */
function Publish() {
    const t = useTranslator();
    const { publish, discard } = useBuilder();
    const publishState = useBuilderState((state) => state.publishState);
    const publishRefusal = useBuilderState((state) => state.publishRefusal);
    const discardState = useBuilderState((state) => state.discardState);
    const hasDraft = useBuilderState((state) => state.status === 'unpublished_changes');
    const publishing = publishState === 'publishing';
    const discarding = discardState === 'discarding';

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
            <button type="button" className={styles.publishButton} disabled={publishing || discarding} onClick={() => void publish()}>
                {publishing ? t('setono_sylius_cms.builder.publishing') : t('setono_sylius_cms.builder.publish')}
            </button>
        </div>
    );
}
