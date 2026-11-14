import { useEffect, useMemo, useState } from 'react';
import type { BuilderApi } from './api';
import styles from './App.module.css';
import { createAutosave, type Autosave } from './autosave';
import type { BuilderConfig } from './config';
import { BuilderProvider, type BuilderSession } from './context';
import { createTranslator, TranslatorProvider, type Translate } from './i18n';
import { createPresence, type Presence } from './presence';
import { createPreviewStore } from './preview';
import { createDiscard, createPublish } from './publishing';
import { createSettingsStore } from './settings';
import { Shell } from './shell/Shell';
import { createBuilderStore } from './store';

export interface AppProps {
    config: BuilderConfig;
    api: BuilderApi;
    /** How long after the last edit autosave sends the changes, in milliseconds. */
    autosaveDelay?: number;
}

type Phase = { name: 'loading' } | { name: 'failed' } | { name: 'ready'; session: BuilderSession; translate: Translate };

/** Loads the registry and the entry, then opens the entry in the builder. */
export function App({ config, api, autosaveDelay }: AppProps) {
    const [phase, setPhase] = useState<Phase>({ name: 'loading' });
    const initialTranslate = useMemo(() => createTranslator(config.strings ?? {}), [config]);

    useEffect(() => {
        let active = true;
        let autosave: Autosave | undefined;
        let presence: Presence | undefined;
        let unsubscribeSettings: (() => void) | undefined;

        Promise.all([api.fetchRegistry(), api.fetchEntry()]).then(
            ([registry, { entry, settings: entrySettings, status, lockToken, editors }]) => {
                if (!active) {
                    return;
                }

                const store = createBuilderStore({
                    locale: config.locale,
                    blockTypes: registry.blockTypes,
                    contentTypes: registry.contentTypes,
                    entry,
                    status,
                    lockToken,
                });
                autosave = createAutosave(store, (changes, token) => api.saveChanges(changes, token), { delay: autosaveDelay });
                // Who else has the entry open. It also pulls in what they saved, once the working copy has moved on
                presence = createPresence(store, autosave, () => api.presence(), editors);

                // The preview shows the entry in one of its own channels, which follow the settings as they change.
                const settings = createSettingsStore(entrySettings, (changes) => api.saveSettings(changes));
                const entryChannels = (codes: string[]) => config.channels.filter(({ code }) => codes.includes(code));
                const preview = createPreviewStore(entryChannels(entrySettings.channels));
                unsubscribeSettings = settings.subscribe((state, previous) => {
                    if (state.settings.channels !== previous.settings.channels) {
                        preview.getState().setChannels(entryChannels(state.settings.channels));
                    }
                });

                setPhase({
                    name: 'ready',
                    translate: createTranslator({ ...config.strings, ...registry.strings }),
                    session: {
                        store,
                        backUrl: config.backUrl,
                        publish: createPublish(store, autosave, () => api.publish()),
                        discard: createDiscard(store, autosave, () => api.discard()),
                        preview,
                        // The page renders what the server holds, so a new page waits for the unsaved changes
                        createPreviewUrl: async (request) => {
                            await autosave?.flush();

                            return api.createPreviewUrl(request);
                        },
                        renderPreview: (token, blocks) => api.renderPreview(token, blocks),
                        settings,
                        presence: presence.store,
                        channels: config.channels,
                        customerGroups: config.customerGroups,
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
            unsubscribeSettings?.();
        };
    }, [api, config, autosaveDelay]);

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

    return (
        <TranslatorProvider value={phase.translate}>
            <BuilderProvider value={phase.session}>
                <Shell />
            </BuilderProvider>
        </TranslatorProvider>
    );
}
