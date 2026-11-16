import { useEffect, useMemo, useState } from 'react';
import { createUrlsApi, type BuilderApi, type UrlsApi } from './api';
import styles from './App.module.css';
import { createAutosave, type Autosave } from './autosave';
import type { BuilderConfig } from './config';
import { BuilderProvider, type BuilderSession } from './context';
import { createTranslator, TranslatorProvider, type Translate } from './i18n';
import { createLeaving } from './leaving';
import { createMediaApi, type MediaApi } from './media/api';
import type { MediaLimits } from './media/config';
import { MediaPickerProvider } from './media/picker';
import { createPresence, type Presence } from './presence';
import { createPreviewStore } from './preview';
import { createCancelSchedule, createDiscard, createPublish, createReload, createSchedule } from './publishing';
import { createSettingsStore } from './settings';
import { createSlugFollower, type SlugFollower } from './slugs';
import { Shell } from './shell/Shell';
import { createBuilderStore } from './store';
import { findBlock } from './tree';
import { createUrlsStore, createUrlsSync, type UrlsSync } from './urls';
import { createValidation, createValidationStore, type Validation } from './validation';

export interface AppProps {
    config: BuilderConfig;
    api: BuilderApi;
    /** The endpoints of the entry's URLs, made from the config's when left out. null leaves the slug unfollowed. */
    urlsApi?: UrlsApi | null;
    /** How long after the last edit autosave sends the changes, in milliseconds. */
    autosaveDelay?: number;
    /** Goes to another page, which leaving the builder does. */
    navigate?: (url: string) => void;
    /** The media library's endpoints, which the media picker uses, made from the config's when left out. */
    mediaApi?: MediaApi;
}

const goTo = (url: string): void => window.location.assign(url);

type Phase = { name: 'loading' } | { name: 'failed' } | { name: 'ready'; session: BuilderSession; translate: Translate; media: MediaLimits | null };

/** Loads the registry and the entry, then opens the entry in the builder. */
export function App({ config, api, urlsApi, autosaveDelay, navigate = goTo, mediaApi }: AppProps) {
    const [phase, setPhase] = useState<Phase>({ name: 'loading' });
    const initialTranslate = useMemo(() => createTranslator(config.strings ?? {}), [config]);
    const urlsEndpoints = useMemo(() => (urlsApi === undefined ? createUrlsApi(config) : urlsApi), [config, urlsApi]);
    const mediaEndpoints = useMemo(
        () => mediaApi ?? (config.media === undefined ? null : createMediaApi({ csrfToken: config.csrfToken, endpoints: config.media.endpoints })),
        [config, mediaApi],
    );

    useEffect(() => {
        let active = true;
        let autosave: Autosave | undefined;
        let presence: Presence | undefined;
        let validation: Validation | undefined;
        let urlsSync: UrlsSync | undefined;
        let slugFollower: SlugFollower | undefined;
        let unsubscribeSettings: (() => void) | undefined;
        let unsubscribeLock: (() => void) | undefined;

        Promise.all([api.fetchRegistry(), api.fetchEntry()]).then(
            ([registry, { entry, settings: entrySettings, status, lockToken, editors, schedule }]) => {
                if (!active) {
                    return;
                }

                const store = createBuilderStore({
                    locale: config.locale,
                    locales: config.locales ?? [],
                    blockTypes: registry.blockTypes,
                    contentTypes: registry.contentTypes,
                    conditionKinds: registry.conditionKinds,
                    entry,
                    status,
                    lockToken,
                    schedule: schedule ?? null,
                });
                // Opened at a block, such as where a problem is fixed, it starts with that block selected
                const opened = config.block ?? null;
                if (opened !== null && findBlock(entry.tree.blocks, opened) !== undefined) {
                    store.getState().selectBlock(opened);
                }

                autosave = createAutosave(store, (changes, token) => api.saveChanges(changes, token), { delay: autosaveDelay });
                // Who else has the entry open. It also pulls in what they saved, once the working copy has moved on
                presence = createPresence(store, autosave, () => api.presence(), editors);

                // Invalid blocks are flagged from the start, and again after each save
                const validationStore = createValidationStore();
                validation = createValidation(store, validationStore, () => api.validate());
                void validation.validate();

                // A content type with its own URL has a slug, which follows the title, and the URL & SEO tab
                const contentType = registry.contentTypes.find(({ code }) => code === entry.contentType);
                const urlPrefix = contentType?.prefix ?? null;
                const urlsStore = createUrlsStore();
                if (urlPrefix !== null && urlsEndpoints !== null) {
                    urlsSync = createUrlsSync(store, urlsStore, () => urlsEndpoints.fetchUrls());
                    void urlsSync.refresh();
                    slugFollower = createSlugFollower(store, (title) => urlsEndpoints.generateSlug(title));
                }
                const follower = slugFollower;

                // The preview shows the entry in one of its own channels, which follow the settings as they change.
                const settings = createSettingsStore(entrySettings, (changes) => api.saveSettings(changes));
                const entryChannels = (codes: string[]) => config.channels.filter(({ code }) => codes.includes(code));
                const preview = createPreviewStore(entryChannels(entrySettings.channels), config.preview);
                unsubscribeSettings = settings.subscribe((state, previous) => {
                    if (state.settings.channels !== previous.settings.channels) {
                        preview.getState().setChannels(entryChannels(state.settings.channels));
                    }
                });

                // Back and Esc leave once the unsaved changes are sent, and stay when they can't be
                const leaving = createLeaving(store, autosave, () => navigate(config.backUrl));

                // A save refused because a colleague scheduled a publish loads the working copy again, read-only
                const reload = createReload(store, () => api.fetchEntry());
                unsubscribeLock = store.subscribe((state, previous) => {
                    if (state.saveState === 'locked' && previous.saveState !== 'locked') {
                        void reload();
                    }
                });

                setPhase({
                    name: 'ready',
                    translate: createTranslator({ ...config.strings, ...registry.strings }),
                    media: registry.media ?? null,
                    session: {
                        store,
                        validation: validationStore,
                        backUrl: config.backUrl,
                        leave: () => void leaving.getState().leave(),
                        leaving,
                        overviewUrl: config.overviewUrl,
                        publish: createPublish(store, autosave, () => api.publish(), validationStore),
                        discard: createDiscard(store, autosave, () => api.discard()),
                        schedule: createSchedule(store, autosave, (at) => api.schedule(at), validationStore),
                        cancelSchedule: createCancelSchedule(store, () => api.cancelSchedule()),
                        reload,
                        preview,
                        // The page renders what the server holds, so a new page waits for the unsaved changes, and for
                        // the settings in flight, which decide where it lives, such as at its channel's root as a homepage
                        createPreviewUrl: async (request) => {
                            await autosave?.flush();
                            await settings.getState().settled();

                            return api.createPreviewUrl(request);
                        },
                        renderPreview: (token, blocks) => api.renderPreview(token, blocks),
                        settings,
                        presence: presence.store,
                        channels: config.channels,
                        locales: config.locales ?? [],
                        customerGroups: config.customerGroups,
                        urlPrefix,
                        homepage: contentType?.homepage ?? false,
                        urls: urlsStore,
                        titleChanged: (locale, before) => follower?.titleChanged(locale, before),
                    },
                });
            },
            () => {
                if (active) {
                    setPhase({ name: 'failed' });
                }
            },
        );

        return () => {
            active = false;
            autosave?.dispose();
            presence?.dispose();
            validation?.dispose();
            urlsSync?.dispose();
            slugFollower?.dispose();
            unsubscribeSettings?.();
            unsubscribeLock?.();
        };
    }, [api, urlsEndpoints, config, autosaveDelay, navigate]);

    if (phase.name !== 'ready') {
        return (
            <div className={styles.screen}>
                {phase.name === 'loading' ? (
                    <p role="status">{initialTranslate('setono_sylius_cms.builder.loading')}</p>
                ) : (
                    <p role="alert">{initialTranslate('setono_sylius_cms.builder.load_failed')}</p>
                )}
            </div>
        );
    }

    // Media fields pick from the media library, which needs its endpoints from the config and the upload limit from the registry
    const media = config.media;

    return (
        <TranslatorProvider value={phase.translate}>
            <BuilderProvider value={phase.session}>
                {media === undefined || mediaEndpoints === null || phase.media === null ? (
                    <Shell />
                ) : (
                    <MediaPickerProvider api={mediaEndpoints} config={{ locales: media.locales, media: phase.media }}>
                        <Shell />
                    </MediaPickerProvider>
                )}
            </BuilderProvider>
        </TranslatorProvider>
    );
}
