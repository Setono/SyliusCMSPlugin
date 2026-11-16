import { useEffect, useRef, useState } from 'react';
import { envelope, openEnvelope, type FromBridge } from '../bridge/protocol';
import { useBuilder, useBuilderState, usePreviewState } from '../context';
import { useTranslator } from '../i18n';
import { hasTranslation } from '../locales';
import { createPreviewSync, type PreviewSync } from '../previewSync';
import type { BlockSpot } from '../tree';
import { BlockPickerAt, soleBlockType } from './BlockPicker';
import styles from './Preview.module.css';
import { ScheduleBanner } from './ScheduleBanner';
import { TranslationBanner } from './TranslationBanner';

type Frame = { state: 'waiting' } | { state: 'loaded'; url: string; version: number } | { state: 'failed' };

/** The block picker opened by + in the preview, at a point in the builder's viewport. */
interface Picker {
    spot: BlockSpot;
    x: number;
    y: number;
}

export interface PreviewProps {
    className?: string | undefined;
    /** Takes the editor to the selected block's settings, from the toolbar in the preview. */
    onOpenSettings?: (() => void) | undefined;
}

/**
 * The centre pane: the real shop page with the working copy, in an iframe from the admin's own host. It loads with a
 * new preview token when the channel or locale changes. Edits are patched into the page by the bridge in it, and the
 * page reloads only for what the bridge can't patch in. In a locale the entry has no translation in, a banner shows
 * instead, because the entry has no page there.
 */
export function Preview({ className, onOpenSettings }: PreviewProps) {
    const t = useTranslator();
    const { store, createPreviewUrl, renderPreview, leave } = useBuilder();
    const channel = usePreviewState((state) => state.channel);
    const width = usePreviewState((state) => state.width);
    const locale = useBuilderState((state) => state.locale);
    const translated = useBuilderState((state) => hasTranslation(state.entry, state.locale));
    const [frame, setFrame] = useState<Frame>({ state: 'waiting' });
    const [reloads, setReloads] = useState(0);
    const [picker, setPicker] = useState<Picker | null>(null);
    const iframe = useRef<HTMLIFrameElement>(null);
    const sync = useRef<PreviewSync | null>(null);
    const openSettings = useRef(onOpenSettings);
    openSettings.current = onOpenSettings;

    useEffect(() => {
        const created = createPreviewSync({
            store,
            strings: {
                selectParent: t('setono_sylius_cms.builder.select_parent'),
                settings: t('setono_sylius_cms.builder.block_settings'),
                delete: t('setono_sylius_cms.builder.delete_block'),
                addBefore: t('setono_sylius_cms.builder.add_block_above'),
                addAfter: t('setono_sylius_cms.builder.add_block_below'),
                moveUp: t('setono_sylius_cms.builder.move_up'),
                moveDown: t('setono_sylius_cms.builder.move_down'),
                duplicate: t('setono_sylius_cms.builder.duplicate'),
                formatting: t('setono_sylius_cms.builder.rich_text.toolbar'),
                bold: t('setono_sylius_cms.builder.rich_text.bold'),
                italic: t('setono_sylius_cms.builder.rich_text.italic'),
                underline: t('setono_sylius_cms.builder.rich_text.underline'),
                strike: t('setono_sylius_cms.builder.rich_text.strike'),
                code: t('setono_sylius_cms.builder.rich_text.code'),
            },
            render: (token, blocks) => renderPreview(token, blocks),
            reload: () => setReloads((count) => count + 1),
            openBlockPicker: (spot, at) => {
                const { rules, entry } = store.getState();
                const only = soleBlockType(rules, entry.tree.blocks, spot.parent);
                if (only !== undefined) {
                    store.getState().addBlock(only.code, spot.parent, spot.index);

                    return;
                }

                const box = iframe.current?.getBoundingClientRect();
                setPicker({ spot, x: (box?.left ?? 0) + at.x, y: (box?.top ?? 0) + at.y });
            },
            openSettings: () => openSettings.current?.(),
            leave,
        });
        sync.current = created;

        return () => {
            created.dispose();
            sync.current = null;
        };
    }, [store, renderPreview, leave, t]);

    useEffect(() => {
        if (channel === null) {
            return undefined;
        }

        sync.current?.disconnect();
        if (!translated) {
            setFrame({ state: 'waiting' });

            return undefined;
        }

        let active = true;

        createPreviewUrl({ channel, locale }).then(
            (url) => {
                if (active) {
                    // A new version remounts the iframe, so it reloads even when the URL is the same.
                    setFrame((previous) => ({ state: 'loaded', url, version: (previous.state === 'loaded' ? previous.version : 0) + 1 }));
                }
            },
            () => {
                if (active) {
                    setFrame({ state: 'failed' });
                }
            },
        );

        return () => {
            active = false;
        };
    }, [createPreviewUrl, channel, locale, translated, reloads]);

    useEffect(() => {
        if (frame.state !== 'loaded') {
            return undefined;
        }

        const url = new URL(frame.url, window.location.href);
        const token = url.searchParams.get('_cms_preview') ?? '';

        // The bridge talks to the builder only by postMessage, and only the page in the preview is listened to
        const onMessage = (event: MessageEvent): void => {
            const target = iframe.current?.contentWindow;
            const message = openEnvelope<FromBridge>(event.data);
            if (target === null || target === undefined || event.source !== target || event.origin !== url.origin || message === null) {
                return;
            }

            if (message.type === 'ready') {
                sync.current?.connect((reply) => target.postMessage(envelope(reply), url.origin), token);
            } else {
                sync.current?.receive(message);
            }
        };

        window.addEventListener('message', onMessage);

        return () => {
            window.removeEventListener('message', onMessage);
        };
    }, [frame]);

    return (
        <main className={[className, styles.preview].filter(Boolean).join(' ')} data-width={width}>
            <ScheduleBanner />
            <TranslationBanner />
            {frame.state === 'loaded' && <iframe ref={iframe} key={frame.version} className={styles.frame} title={t('setono_sylius_cms.builder.preview')} src={frame.url} />}
            {frame.state === 'failed' && (
                <p role="alert" className={styles.failed}>
                    {t('setono_sylius_cms.builder.preview_failed')}
                </p>
            )}
            <BlockPickerAt spot={picker?.spot ?? null} onClose={() => setPicker(null)}>
                <span className={styles.pickerAnchor} style={{ left: picker?.x ?? 0, top: picker?.y ?? 0 }} />
            </BlockPickerAt>
        </main>
    );
}
