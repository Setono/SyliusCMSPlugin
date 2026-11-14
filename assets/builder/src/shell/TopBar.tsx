import { useBuilder, useBuilderState, usePreviewState } from '../context';
import { useTranslator, type MessageKey } from '../i18n';
import type { EntryStatus } from '../types';
import { ArrowLeftIcon, DesktopIcon, MobileIcon, RedoIcon, UndoIcon } from './icons';
import styles from './TopBar.module.css';

const statusLabels: Record<EntryStatus, MessageKey> = {
    never_published: 'setono_sylius_cms.builder.never_published',
    published: 'setono_sylius_cms.builder.published',
    unpublished_changes: 'setono_sylius_cms.builder.unpublished_changes',
};

export function TopBar() {
    const t = useTranslator();
    const { backUrl } = useBuilder();
    const title = useBuilderState((state) => {
        const value = state.entry.translations[state.locale]?.fields.title;

        return typeof value === 'string' ? value : '';
    });
    const status = useBuilderState((state) => state.status);

    return (
        <div className={styles.bar}>
            <a href={backUrl} className={styles.back}>
                <ArrowLeftIcon />
                {t('setono_sylius_cms.builder.back')}
            </a>
            <div className={styles.heading}>
                <h1 className={styles.title}>{title}</h1>
                <span className={styles.status} data-status={status}>
                    {t(statusLabels[status])}
                </span>
            </div>
            <PreviewChannel />
            <PreviewWidth />
            <UndoRedo />
            <SaveState />
            <Publish />
        </div>
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

function SaveState() {
    const t = useTranslator();
    const saveState = useBuilderState((state) => state.saveState);
    const text = {
        idle: '',
        saving: t('setono_sylius_cms.builder.saving'),
        saved: t('setono_sylius_cms.builder.saved'),
        failed: t('setono_sylius_cms.builder.save_failed'),
    }[saveState];

    return (
        <span role="status" className={styles.saveState} data-state={saveState}>
            {text}
        </span>
    );
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
