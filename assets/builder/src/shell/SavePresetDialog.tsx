import * as Dialog from '@radix-ui/react-dialog';
import { useEffect, useId, useState, type FormEvent } from 'react';
import { useBuilder } from '../context';
import formStyles from '../form/Form.module.css';
import { useTranslator } from '../i18n';
import type { PresetIndex, PresetListing } from '../types';
import styles from './SavePresetDialog.module.css';

export interface SavePresetDialogProps {
    /** The keys of the blocks to save, the selected block or a ⌘-click selection, or null while the dialog is closed. */
    blocks: readonly string[] | null;
    onClose: () => void;
}

/**
 * ★ "Save as preset": saves the selected blocks, or a ⌘-click selection, as an editor preset with a name, a category,
 * which is free text suggested from the categories in use, and a description, or replaces an editor preset's blocks
 * with them ("Replace <name>"). The server saves them as it has them, in tree order, a block inside another selected
 * block once, and it takes effect at once. See "Editor presets" in docs/spec/layouts-and-presets.md
 */
export function SavePresetDialog({ blocks, onClose }: SavePresetDialogProps) {
    const t = useTranslator();

    return (
        <Dialog.Root open={blocks !== null} onOpenChange={(open) => open || onClose()}>
            <Dialog.Portal>
                <Dialog.Overlay className={styles.overlay} />
                <Dialog.Content className={styles.dialog}>
                    <Dialog.Title className={styles.title}>{t('setono_sylius_cms.builder.save_as_preset')}</Dialog.Title>
                    <Dialog.Description className={styles.description}>{t('setono_sylius_cms.builder.save_as_preset_help')}</Dialog.Description>
                    {blocks !== null && <SavePresetForm blocks={blocks} onSaved={onClose} />}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function SavePresetForm({ blocks, onSaved }: { blocks: readonly string[]; onSaved: () => void }) {
    const t = useTranslator();
    const id = useId();
    const { presets } = useBuilder();
    const editing = presets?.editing ?? null;
    const [index, setIndex] = useState<PresetIndex | null>(null);
    const [name, setName] = useState('');
    const [category, setCategory] = useState('');
    const [description, setDescription] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // The categories in use, which the category suggests, and the editor presets it can replace
    useEffect(() => {
        if (presets === null) {
            return undefined;
        }

        let active = true;
        presets.list(null).then(
            (listed) => active && setIndex(listed),
            () => undefined,
        );

        return () => {
            active = false;
        };
    }, [presets]);

    const save = async (work: () => Promise<unknown>): Promise<void> => {
        setSaving(true);
        setError(null);
        try {
            await work();
            onSaved();
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : String(failure));
            setSaving(false);
        }
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        if (editing !== null) {
            void save(() => editing.create(blocks, { name, category: category.trim() === '' ? null : category, description: description.trim() === '' ? null : description }));
        }
    };

    const replaceable: PresetListing[] = (index?.presets ?? []).filter(({ fromCode }) => !fromCode);

    return (
        <>
            <form className={styles.form} onSubmit={submit}>
                <div className={formStyles.field}>
                    <label htmlFor={`${id}-name`} className={formStyles.label}>
                        {t('setono_sylius_cms.builder.preset_name')}
                    </label>
                    <input id={`${id}-name`} className={formStyles.input} value={name} required onChange={(event) => setName(event.currentTarget.value)} />
                </div>
                <div className={formStyles.field}>
                    <label htmlFor={`${id}-category`} className={formStyles.label}>
                        {t('setono_sylius_cms.builder.preset_category')}
                    </label>
                    <input id={`${id}-category`} className={formStyles.input} value={category} list={`${id}-categories`} onChange={(event) => setCategory(event.currentTarget.value)} />
                    <datalist id={`${id}-categories`}>
                        {(index?.categories ?? []).map((suggested) => (
                            <option key={suggested} value={suggested} />
                        ))}
                    </datalist>
                </div>
                <div className={formStyles.field}>
                    <label htmlFor={`${id}-description`} className={formStyles.label}>
                        {t('setono_sylius_cms.builder.preset_description')}
                    </label>
                    <textarea id={`${id}-description`} className={formStyles.textarea} rows={3} value={description} onChange={(event) => setDescription(event.currentTarget.value)} />
                </div>
                {error !== null && (
                    <p role="alert" className={styles.error}>
                        {error}
                    </p>
                )}
                <div className={styles.actions}>
                    <button type="submit" className={styles.primary} disabled={saving || editing === null}>
                        {t('setono_sylius_cms.builder.save_preset')}
                    </button>
                </div>
            </form>
            {replaceable.length > 0 && editing !== null && (
                <section className={styles.replace} aria-labelledby={`${id}-replace`}>
                    <h3 id={`${id}-replace`} className={styles.subheading}>
                        {t('setono_sylius_cms.builder.replace_a_preset')}
                    </h3>
                    <ul className={styles.replaceList}>
                        {replaceable.map((preset) => (
                            <li key={preset.id}>
                                <button type="button" className={styles.secondary} disabled={saving} onClick={() => void save(() => editing.replace(preset.id, blocks))}>
                                    {t('setono_sylius_cms.builder.replace_preset', { '%name%': preset.name })}
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </>
    );
}
