import * as Dialog from '@radix-ui/react-dialog';
import { useState, type FormEvent } from 'react';
import { useTranslator } from '../i18n';
import { useMediaLibraryContext } from './context';
import { LabelledControl } from './LabelledControl';
import dialogStyles from './MediaDialog.module.css';
import styles from './MediaLibrary.module.css';
import type { MediaItem } from './types';
import { checkFile, clientRefusalMessage } from './upload';
import { UsedIn } from './UsedIn';

/**
 * Replace file: it lists every use of the media item and says that the new file takes effect at once wherever it's
 * used, keeping the media item's name, alt text and focal point. The new file is checked like an upload, and must be of
 * the same kind, which the server checks. See "Replacing a file" in docs/spec/media.md and ADR 0013.
 */
export function ReplaceFileDialog({ item, onClose }: { item: MediaItem; onClose: () => void }) {
    const t = useTranslator();
    const { store, config } = useMediaLibraryContext();
    const [file, setFile] = useState<File | null>(null);
    const [replacing, setReplacing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const replace = async (event: FormEvent): Promise<void> => {
        event.preventDefault();
        if (file === null) {
            return;
        }

        const refusal = checkFile(file, config.media);
        if (refusal !== null) {
            setError(clientRefusalMessage(t, file.name, refusal, config.media));

            return;
        }

        setReplacing(true);
        setError(null);

        try {
            await store.getState().replace(item.uuid, file);
            onClose();
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : String(failure));
            setReplacing(false);
        }
    };

    return (
        <Dialog.Root open onOpenChange={(open) => open || onClose()}>
            <Dialog.Portal>
                <Dialog.Overlay className={dialogStyles.overlay} />
                <Dialog.Content className={dialogStyles.dialog}>
                    <Dialog.Title className={dialogStyles.title}>{t('setono_sylius_cms.builder.media.replace_file')}</Dialog.Title>
                    <Dialog.Description className={dialogStyles.description}>{t('setono_sylius_cms.builder.media.replace_description')}</Dialog.Description>
                    <UsedIn uuid={item.uuid} />
                    <form className={dialogStyles.form} onSubmit={(event) => void replace(event)}>
                        <LabelledControl label={t('setono_sylius_cms.builder.media.new_file')}>
                            {(id) => (
                                <input
                                    id={id}
                                    type="file"
                                    accept={config.media.types[item.kind].join(',')}
                                    onChange={(event) => {
                                        setFile(event.target.files?.[0] ?? null);
                                        setError(null);
                                    }}
                                />
                            )}
                        </LabelledControl>
                        {error !== null && (
                            <p className={styles.refused} role="alert">
                                {error}
                            </p>
                        )}
                        <div className={dialogStyles.actions}>
                            <button type="button" className={styles.secondary} onClick={onClose}>
                                {t('setono_sylius_cms.builder.media.cancel')}
                            </button>
                            <button type="submit" className={styles.primary} disabled={file === null || replacing}>
                                {replacing ? t('setono_sylius_cms.builder.media.replacing') : t('setono_sylius_cms.builder.media.replace')}
                            </button>
                        </div>
                    </form>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
