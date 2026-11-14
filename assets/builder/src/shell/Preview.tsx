import { useEffect, useState } from 'react';
import { useBuilder, useBuilderState, usePreviewState } from '../context';
import { useTranslator } from '../i18n';
import styles from './Preview.module.css';

type Frame = { state: 'waiting' } | { state: 'loaded'; url: string; version: number } | { state: 'failed' };

/**
 * The centre pane: the real shop page with the working copy, in an iframe from the admin's own host. It loads with a
 * new preview token whenever the channel or locale changes, and reloads after each saved change.
 */
export function Preview({ className }: { className?: string | undefined }) {
    const t = useTranslator();
    const { store, createPreviewUrl } = useBuilder();
    const channel = usePreviewState((state) => state.channel);
    const width = usePreviewState((state) => state.width);
    const locale = useBuilderState((state) => state.locale);
    const [frame, setFrame] = useState<Frame>({ state: 'waiting' });

    useEffect(() => {
        if (channel === null) {
            return undefined;
        }

        let active = true;
        let latest = 0;

        const load = (): void => {
            const request = ++latest;
            const isLatest = (): boolean => active && request === latest;

            createPreviewUrl({ channel, locale }).then(
                (url) => {
                    if (isLatest()) {
                        // A new version remounts the iframe, so it reloads even when the URL is the same.
                        setFrame((previous) => ({ state: 'loaded', url, version: (previous.state === 'loaded' ? previous.version : 0) + 1 }));
                    }
                },
                () => {
                    if (isLatest()) {
                        setFrame({ state: 'failed' });
                    }
                },
            );
        };

        load();

        // Once everything is saved, the server renders what the builder holds.
        const unsubscribe = store.subscribe((state, previous) => {
            if (state.saveState === 'saved' && previous.saveState !== 'saved') {
                load();
            }
        });

        return () => {
            active = false;
            unsubscribe();
        };
    }, [store, createPreviewUrl, channel, locale]);

    return (
        <main className={[className, styles.preview].filter(Boolean).join(' ')} data-width={width}>
            {frame.state === 'loaded' && <iframe key={frame.version} className={styles.frame} title={t('setono_sylius_cms.builder.preview')} src={frame.url} />}
            {frame.state === 'failed' && (
                <p role="alert" className={styles.failed}>
                    {t('setono_sylius_cms.builder.preview_failed')}
                </p>
            )}
        </main>
    );
}
