import { useEffect, useMemo, useState } from 'react';
import type { BuilderApi } from './api';
import styles from './App.module.css';
import { createAutosave, type Autosave } from './autosave';
import type { BuilderConfig } from './config';
import { BuilderProvider, type BuilderSession } from './context';
import { createTranslator, TranslatorProvider, type Translate } from './i18n';
import { createPublish } from './publishing';
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

        Promise.all([api.fetchRegistry(), api.fetchEntry()]).then(
            ([registry, { entry, status }]) => {
                if (!active) {
                    return;
                }

                const store = createBuilderStore({ locale: config.locale, blockTypes: registry.blockTypes, entry, status });
                autosave = createAutosave(store, (changes) => api.saveChanges(changes), { delay: autosaveDelay });

                setPhase({
                    name: 'ready',
                    translate: createTranslator({ ...config.strings, ...registry.strings }),
                    session: {
                        store,
                        backUrl: config.backUrl,
                        publish: createPublish(store, autosave, () => api.publish()),
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
