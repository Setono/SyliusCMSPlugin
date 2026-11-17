import * as Dialog from '@radix-ui/react-dialog';
import { useRef, useState } from 'react';
import { useTranslator } from '../i18n';
import { useMediaLibraryContext } from './context';
import dialogStyles from './MediaDialog.module.css';
import styles from './MediaLibrary.module.css';
import type { MediaItem } from './types';

/**
 * Confirms deleting a media item nothing uses, with its file, and shows why it couldn't be, such as a listener's veto.
 * Cancel has the focus, so Enter keeps the media item. See "Deleting" in docs/spec/media.md.
 */
export function DeleteMediaItemDialog({ item, onClose }: { item: MediaItem; onClose: () => void }) {
    const t = useTranslator();
    const { store } = useMediaLibraryContext();
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const cancel = useRef<HTMLButtonElement>(null);

    const remove = async (): Promise<void> => {
        setDeleting(true);
        setError(null);

        try {
            // A deleted media item closes its details, and this with them
            await store.getState().remove(item.uuid);
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : String(failure));
            setDeleting(false);
        }
    };

    return (
        <Dialog.Root open onOpenChange={(open) => open || onClose()}>
            <Dialog.Portal>
                <Dialog.Overlay className={dialogStyles.overlay} />
                <Dialog.Content
                    className={dialogStyles.dialog}
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        cancel.current?.focus();
                    }}
                >
                    <Dialog.Title className={dialogStyles.title}>{t('setono_sylius_cms.builder.media.delete_title', { '%name%': item.name })}</Dialog.Title>
                    <Dialog.Description className={dialogStyles.description}>{t('setono_sylius_cms.builder.media.delete_description')}</Dialog.Description>
                    {error !== null && (
                        <p className={styles.refused} role="alert">
                            {error}
                        </p>
                    )}
                    <div className={dialogStyles.actions}>
                        <button ref={cancel} type="button" className={styles.secondary} onClick={onClose}>
                            {t('setono_sylius_cms.builder.media.cancel')}
                        </button>
                        <button type="button" className={styles.danger} disabled={deleting} onClick={() => void remove()}>
                            {deleting ? t('setono_sylius_cms.builder.media.deleting') : t('setono_sylius_cms.builder.media.delete')}
                        </button>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
