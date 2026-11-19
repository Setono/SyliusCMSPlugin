import { useEffect, useRef, useState } from 'react';
import { envelope, openEnvelope, type FromBridge } from '../bridge/protocol';
import { useBuilder, useBuilderState, usePreviewState, useSettingsState } from '../context';
import { useTranslator } from '../i18n';
import { hasTranslation } from '../locales';
import { stylesheetsOf } from '../presets';
import { createPreviewSync, type PreviewSync } from '../previewSync';
import type { BlockSpot } from '../tree';
import { BlockPickerAt, soleBlockType } from './BlockPicker';
import styles from './Preview.module.css';
import { ScheduleBanner } from './ScheduleBanner';
import { TranslationBanner } from './TranslationBanner';

/** A loaded frame shows the page-th page asked for. That keys the iframe, so a new page reloads it even at the same URL. */
type Frame = { state: 'waiting' } | { state: 'loaded'; url: string; page: number } | { state: 'failed' };

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
 * new preview token when the channel, the locale, Shown to or Date changes, or whether the page is the channel's
 * homepage, which moves it to the channel's root, and a snippet's when its placement or sample object changes. Edits are patched into the page by the bridge in it, and the
 * page reloads only for what the bridge can't patch in. In a locale the entry has no translation in, a banner shows
 * instead, because the entry has no page there. An entry whose content type has no URL of its own, other than a
 * snippet, such as a FAQ item, has no page at all, so the pane says so and no preview is asked for.
 */
export function Preview({ className, onOpenSettings }: PreviewProps) {
    const t = useTranslator();
    const { store, preview, createPreviewUrl, renderPreview, leave, placements, hasOwnUrl } = useBuilder();
    const channel = usePreviewState((state) => state.channel);
    const width = usePreviewState((state) => state.width);
    const shownTo = usePreviewState((state) => state.shownTo);
    const date = usePreviewState((state) => state.date);
    const placement = usePreviewState((state) => state.placement);
    const sample = usePreviewState((state) => state.sample);
    // A snippet shows on the host page of a placement, with a sample object
    const snippet = placements !== null;
    // Any other entry shows on its own page, which an entry without a URL doesn't have
    const pageless = !hasOwnUrl && !snippet;
    const locale = useBuilderState((state) => state.locale);
    const translated = useBuilderState((state) => hasTranslation(state.entry, state.locale));
    // A page lives at its channel's root while it's the channel's homepage, so the preview moves when that changes
    const homepage = useSettingsState((state) => channel !== null && state.settings.homepageOf.includes(channel));
    const [frame, setFrame] = useState<Frame>({ state: 'waiting' });
    // Counts the pages asked for. The page shown stays until the next one is in place, but its bridge is connected
    // only while it's the last one asked for. The next page renders what the server holds when it's asked for, and
    // gets the changes made after that patched in once its bridge is ready, so a bridge that gets ready in the old
    // page meanwhile, such as one slow to load, would take those changes to a page that's going away
    const asked = useRef(0);
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
                const only = soleBlockType(rules, entry, spot.parent);
                if (only !== undefined) {
                    store.getState().addBlock(only.code, spot.parent, spot.index);

                    return;
                }

                const box = iframe.current?.getBoundingClientRect();
                setPicker({ spot, x: (box?.left ?? 0) + at.x, y: (box?.top ?? 0) + at.y });
            },
            openSettings: () => openSettings.current?.(),
            leave,
            flagUnresolvedLinks: (keys) => preview.getState().setUnresolvedLinks(keys),
        });
        sync.current = created;

        return () => {
            created.dispose();
            sync.current = null;
        };
    }, [store, preview, renderPreview, leave, t]);

    useEffect(() => {
        if (channel === null || pageless) {
            return undefined;
        }

        sync.current?.disconnect();
        const page = ++asked.current;
        if (!translated) {
            setFrame({ state: 'waiting' });

            return undefined;
        }

        let active = true;

        createPreviewUrl({ channel, locale, shownTo, date, ...(snippet ? { placement, sample } : {}) }).then(
            (url) => {
                if (active) {
                    setFrame({ state: 'loaded', url, page });
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
    }, [createPreviewUrl, channel, locale, shownTo, date, snippet, pageless, placement, sample, translated, homepage, reloads]);

    // Sources count what they yield under the token of the page shown, so in its channel, locale, Shown to and Date
    useEffect(() => {
        const token = frame.state === 'loaded' ? new URL(frame.url, window.location.href).searchParams.get('_cms_preview') : null;
        preview.getState().setToken(token === '' ? null : token);
    }, [preview, frame]);

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
                if (frame.page !== asked.current) {
                    return;
                }

                sync.current?.connect((reply) => target.postMessage(envelope(reply), url.origin), token);
                // The Presets tab renders presets with the page's stylesheets, which it reads here, from the same origin
                const page = iframe.current?.contentDocument ?? null;
                preview.getState().setPage(page === null ? null : stylesheetsOf(page));
            } else {
                sync.current?.receive(message);
            }
        };

        window.addEventListener('message', onMessage);

        return () => {
            window.removeEventListener('message', onMessage);
        };
    }, [preview, frame]);

    return (
        <main className={[className, styles.preview].filter(Boolean).join(' ')} data-width={width}>
            <ScheduleBanner />
            <TranslationBanner />
            {frame.state === 'loaded' && <iframe ref={iframe} key={frame.page} className={styles.frame} title={t('setono_sylius_cms.builder.preview')} src={frame.url} />}
            {frame.state === 'failed' && (
                <p role="alert" className={styles.failed}>
                    {t('setono_sylius_cms.builder.preview_failed')}
                </p>
            )}
            {pageless && <p className={styles.noPreview}>{t('setono_sylius_cms.builder.no_preview')}</p>}
            <BlockPickerAt spot={picker?.spot ?? null} onClose={() => setPicker(null)}>
                <span className={styles.pickerAnchor} style={{ left: picker?.x ?? 0, top: picker?.y ?? 0 }} />
            </BlockPickerAt>
        </main>
    );
}
