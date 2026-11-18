import { useEffect, useId, useState } from 'react';
import { useBuilder, usePreviewState } from '../context';
import { useTranslator } from '../i18n';
import { groupPresets, presetDocument, type PreviewPage } from '../presets';
import type { PresetListing } from '../types';
import styles from './BlockPicker.module.css';

type Listing = { name: 'loading' } | { name: 'failed' } | { name: 'loaded'; presets: PresetListing[] };

type Message = { role: 'status' | 'alert'; text: string };

export interface PresetsTabProps {
    /** The block whose children the preset's blocks join, or null for the top level. */
    parent: string | null;
    /** Where in that list they go, or at the end. */
    index?: number | undefined;
    /** Called once a preset's blocks are inserted with nothing to tell the editor, which closes the picker. */
    onInserted: () => void;
}

/**
 * The block picker's Presets tab: the code presets and editor presets that fit the spot, grouped by category, with a
 * search and a live render of each in the preview's channel, locale, Shown to and Date, and those left out named with
 * why. A preset with invalid blocks says what's wrong, and a code preset is marked as coming from code. Inserting one
 * asks the server for its blocks, which come upgraded, with new keys and the entry's locales, and says what was left
 * out of them. See "Presets" in docs/spec/layouts-and-presets.md and "Block picker" in docs/spec/builder.md
 */
export function PresetsTab({ parent, index, onInserted }: PresetsTabProps) {
    const t = useTranslator();
    const searchId = useId();
    const leftOutId = useId();
    const { store, presets, renderPreview } = useBuilder();
    const token = usePreviewState((state) => state.token);
    const page = usePreviewState((state) => state.page);
    const [listing, setListing] = useState<Listing>({ name: 'loading' });
    const [query, setQuery] = useState('');
    const [renders, setRenders] = useState<Record<string, string>>({});
    const [message, setMessage] = useState<Message | null>(null);

    useEffect(() => {
        if (presets === null) {
            return undefined;
        }

        let active = true;
        presets.list(parent).then(
            (listed) => active && setListing({ name: 'loaded', presets: listed }),
            () => active && setListing({ name: 'failed' }),
        );

        return () => {
            active = false;
        };
    }, [presets, parent]);

    // Every preset offered renders in one go, under the token of the page the preview shows
    useEffect(() => {
        const blocks = listing.name === 'loaded' ? listing.presets.filter(({ fits }) => fits).flatMap((preset) => preset.blocks) : [];
        if (token === null || blocks.length === 0) {
            return undefined;
        }

        let active = true;
        renderPreview(token, blocks).then(
            (html) => active && setRenders(html),
            () => undefined,
        );

        return () => {
            active = false;
        };
    }, [listing, token, renderPreview]);

    const insert = async (preset: PresetListing): Promise<void> => {
        if (presets === null) {
            return;
        }

        setMessage(null);
        try {
            const inserted = await presets.insert(preset.id);
            if (store.getState().insertBlocks(inserted.blocks, parent, index) === null) {
                setMessage({ role: 'alert', text: t('setono_sylius_cms.builder.preset_does_not_fit') });

                return;
            }

            if (inserted.notices.length > 0) {
                setMessage({ role: 'status', text: inserted.notices.join(' ') });

                return;
            }

            onInserted();
        } catch (error) {
            setMessage({ role: 'alert', text: error instanceof Error ? error.message : String(error) });
        }
    };

    if (listing.name !== 'loaded') {
        return (
            <p role={listing.name === 'loading' ? 'status' : 'alert'} className={styles.empty}>
                {t(listing.name === 'loading' ? 'setono_sylius_cms.builder.loading_presets' : 'setono_sylius_cms.builder.presets_failed')}
            </p>
        );
    }

    const { groups, leftOut } = groupPresets(listing.presets, query);
    // Outside a loaded preview, such as before it loads, a preset renders without the shop's stylesheets
    const shown: PreviewPage = page ?? { base: document.baseURI, stylesheets: '' };

    return (
        <>
            <label htmlFor={searchId} className={styles.visuallyHidden}>
                {t('setono_sylius_cms.builder.search_presets')}
            </label>
            <input id={searchId} type="search" className={styles.search} placeholder={t('setono_sylius_cms.builder.search_presets')} value={query} onChange={(event) => setQuery(event.currentTarget.value)} />
            {message !== null && (
                <p role={message.role} className={message.role === 'alert' ? styles.alert : styles.notice}>
                    {message.text}
                </p>
            )}
            {groups.length === 0 && <p className={styles.empty}>{t('setono_sylius_cms.builder.no_matching_presets')}</p>}
            {groups.map(({ category, presets: grouped }) => {
                const name = category ?? t('setono_sylius_cms.builder.preset_uncategorised');

                return (
                    <section key={category ?? ''} role="group" aria-label={name} className={styles.presetGroup}>
                        <h3 className={styles.leftOutHeading}>{name}</h3>
                        <ul className={styles.presets}>
                            {grouped.map((preset) => {
                                const html = preset.blocks.map(({ key }) => renders[key] ?? '').join('');

                                return (
                                    <li key={preset.id} className={styles.preset}>
                                        <h4 className={styles.presetName}>
                                            {preset.name}
                                            {preset.fromCode && <span className={styles.badge}>{t('setono_sylius_cms.builder.preset_from_code')}</span>}
                                        </h4>
                                        {preset.description !== null && <p className={styles.reason}>{preset.description}</p>}
                                        {preset.issues.length > 0 && (
                                            <ul className={styles.issues} aria-label={t('setono_sylius_cms.builder.preset_invalid')}>
                                                {preset.issues.map((issue) => (
                                                    <li key={issue}>{issue}</li>
                                                ))}
                                            </ul>
                                        )}
                                        <div className={styles.render}>
                                            {html !== '' && <iframe title={t('setono_sylius_cms.builder.preset_preview', { '%name%': preset.name })} srcDoc={presetDocument(shown, html)} sandbox="" tabIndex={-1} />}
                                        </div>
                                        <button type="button" className={styles.insert} onClick={() => void insert(preset)}>
                                            {t('setono_sylius_cms.builder.insert_preset', { '%name%': preset.name })}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                );
            })}
            {leftOut.length > 0 && (
                <>
                    <h3 id={leftOutId} className={styles.leftOutHeading}>
                        {t('setono_sylius_cms.builder.not_available_here')}
                    </h3>
                    <ul className={styles.leftOut} aria-labelledby={leftOutId}>
                        {leftOut.map((preset) => (
                            <li key={preset.id} className={styles.leftOutItem}>
                                <span className={styles.label}>{preset.name}</span>
                                <span className={styles.reason}>{preset.reasons.join(' ')}</span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </>
    );
}
