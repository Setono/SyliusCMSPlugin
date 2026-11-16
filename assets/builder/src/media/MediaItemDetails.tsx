import { useId, useState, type FormEvent } from 'react';
import { useTranslator } from '../i18n';
import { useMediaLibrary, useMediaLibraryContext } from './context';
import { Field } from './Field';
import { FocalPointEditor } from './FocalPointEditor';
import { flattenTree, folderTree } from './folders';
import styles from './MediaLibrary.module.css';
import type { FocalPoint, MediaItem } from './types';
import { formatSize } from './upload';

/**
 * The selected media item: its focal point, name, credit and alt text per locale, saved together, which takes effect at
 * once wherever it's used, and Move, which puts it in another folder. See "Media library" in docs/spec/admin.md.
 */
export function MediaItemDetails({ item }: { item: MediaItem }) {
    const t = useTranslator();
    const { store, config } = useMediaLibraryContext();
    const folders = useMediaLibrary((state) => state.folders);
    const saveState = useMediaLibrary((state) => state.saveState);
    const error = useMediaLibrary((state) => state.error);
    const id = useId();

    const [name, setName] = useState(item.name);
    const [credit, setCredit] = useState(item.credit ?? '');
    const [altTexts, setAltTexts] = useState<Record<string, string>>(() => Object.fromEntries(config.locales.map(({ code }) => [code, item.altTexts[code] ?? ''])));
    const [focalPoint, setFocalPoint] = useState<FocalPoint>(item.focalPoint ?? { x: 0.5, y: 0.5 });
    const [moveTo, setMoveTo] = useState(item.folder ?? '');
    const image = item.kind === 'image';

    const save = (event: FormEvent): void => {
        event.preventDefault();
        void store.getState().save(item.uuid, {
            name,
            credit: credit.trim() === '' ? null : credit,
            ...(image ? { altTexts, focalPoint } : {}),
        });
    };

    return (
        <aside className={styles.details} aria-labelledby={`${id}-heading`}>
            <h2 id={`${id}-heading`} className={styles.detailsHeading}>
                {item.name}
            </h2>

            {image && <FocalPointEditor src={item.url} value={focalPoint} onChange={setFocalPoint} />}

            <form className={styles.form} onSubmit={save}>
                <Field label={t('setono_sylius_cms.builder.media.name')}>
                    {(fieldId) => <input id={fieldId} className={styles.input} value={name} required onChange={(event) => setName(event.target.value)} />}
                </Field>

                <Field label={t('setono_sylius_cms.builder.media.credit')} help={t('setono_sylius_cms.builder.media.credit_help')}>
                    {(fieldId) => <input id={fieldId} className={styles.input} value={credit} onChange={(event) => setCredit(event.target.value)} />}
                </Field>

                {image && (
                    <fieldset className={styles.fieldset}>
                        <legend className={styles.label}>{t('setono_sylius_cms.builder.media.alt_text')}</legend>
                        <span className={styles.help}>{t('setono_sylius_cms.builder.media.alt_text_help')}</span>
                        {config.locales.map(({ code, name: localeName }) => (
                            <Field key={code} label={localeName} variant="locale">
                                {(fieldId) => (
                                    <textarea
                                        id={fieldId}
                                        className={styles.textarea}
                                        rows={2}
                                        value={altTexts[code] ?? ''}
                                        onChange={(event) => setAltTexts((current) => ({ ...current, [code]: event.target.value }))}
                                    />
                                )}
                            </Field>
                        ))}
                    </fieldset>
                )}

                <p className={styles.help}>{t('setono_sylius_cms.builder.media.takes_effect')}</p>
                <div className={styles.actions}>
                    <button type="submit" className={styles.primary} disabled={saveState === 'saving'}>
                        {t('setono_sylius_cms.builder.media.save')}
                    </button>
                    <span className={styles.saveState} role="status">
                        {saveState === 'saving' && t('setono_sylius_cms.builder.media.saving')}
                        {saveState === 'saved' && t('setono_sylius_cms.builder.media.saved')}
                        {saveState === 'failed' && `${t('setono_sylius_cms.builder.media.save_failed')}${error === null ? '' : `: ${error}`}`}
                    </span>
                </div>
            </form>

            <div className={styles.move}>
                <Field label={t('setono_sylius_cms.builder.media.move_to')}>
                    {(fieldId) => (
                        <select id={fieldId} className={styles.select} value={moveTo} onChange={(event) => setMoveTo(event.target.value)}>
                            <option value="">{t('setono_sylius_cms.builder.media.unsorted')}</option>
                            {flattenTree(folderTree(folders)).map(({ folder, depth }) => (
                                <option key={folder.uuid} value={folder.uuid}>
                                    {`${' '.repeat(depth)}${folder.name}`}
                                </option>
                            ))}
                        </select>
                    )}
                </Field>
                <button
                    type="button"
                    className={styles.secondary}
                    disabled={moveTo === (item.folder ?? '')}
                    onClick={() => void store.getState().save(item.uuid, { folder: moveTo === '' ? null : moveTo })}
                >
                    {t('setono_sylius_cms.builder.media.move')}
                </button>
            </div>

            <dl className={styles.file}>
                <dt>{t('setono_sylius_cms.builder.media.file')}</dt>
                <dd>
                    <a href={item.url} target="_blank" rel="noreferrer" title={t('setono_sylius_cms.builder.media.open_file')}>
                        {item.originalFilename}
                    </a>
                </dd>
                <dd>
                    {item.mimeType}, {formatSize(item.size)}
                </dd>
                {item.width !== null && item.height !== null && (
                    <dd>{t('setono_sylius_cms.builder.media.dimensions', { '%width%': String(item.width), '%height%': String(item.height) })}</dd>
                )}
            </dl>
        </aside>
    );
}
