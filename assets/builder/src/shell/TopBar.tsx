import { useBuilder, useBuilderState, usePreviewState } from '../context';
import { useTranslator } from '../i18n';
import { ArrowLeftIcon, DesktopIcon, MobileIcon } from './icons';
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
            <PreviewChannel />
            <PreviewWidth />
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
