import { useBuilder, useBuilderState } from '../context';
import { useTranslator, type MessageKey } from '../i18n';
import type { EntryStatus } from '../types';
import { ArrowLeftIcon } from './icons';
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
            <SaveState />
            <Publish />
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
