import { useBuilder, useBuilderState } from '../context';
import { useTranslator } from '../i18n';
import { ArrowLeftIcon } from './icons';
import styles from './TopBar.module.css';

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
                    {status === 'published' ? t('setono_sylius_cms.builder.published') : t('setono_sylius_cms.builder.never_published')}
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

function Publish() {
    const t = useTranslator();
    const { publish } = useBuilder();
    const publishState = useBuilderState((state) => state.publishState);
    const publishing = publishState === 'publishing';

    return (
        <div className={styles.publish}>
            {publishState === 'failed' && (
                <span role="alert" className={styles.publishFailed}>
                    {t('setono_sylius_cms.builder.publish_failed')}
                </span>
            )}
            <button type="button" className={styles.publishButton} disabled={publishing} onClick={() => void publish()}>
                {publishing ? t('setono_sylius_cms.builder.publishing') : t('setono_sylius_cms.builder.publish')}
            </button>
        </div>
    );
}
