import * as Dialog from '@radix-ui/react-dialog';
import { useRef } from 'react';
import { useBuilder, useLeavingState } from '../context';
import { useTranslator } from '../i18n';
import { saveStateText } from './saveStateText';
// It looks like the clash dialog
import styles from './ClashDialog.module.css';

/**
 * Leaving stayed because the unsaved changes couldn't be sent: it says why, as the save state did, and the editor stays,
 * which Esc does too, or leaves anyway, without them. See "Opening and leaving" in docs/spec/builder.md
 */
export function LeaveDialog() {
    const t = useTranslator();
    const { leaving } = useBuilder();
    const failedSave = useLeavingState((state) => state.failedSave);
    const stay = useRef<HTMLButtonElement>(null);

    return (
        <Dialog.Root open={failedSave !== null} onOpenChange={(open) => open || leaving.getState().stay()}>
            <Dialog.Portal>
                <Dialog.Overlay className={styles.overlay} />
                <Dialog.Content
                    className={styles.dialog}
                    onOpenAutoFocus={(event) => {
                        // Enter keeps the changes
                        event.preventDefault();
                        stay.current?.focus();
                    }}
                >
                    <Dialog.Title className={styles.title}>{t('setono_sylius_cms.builder.leave_unsaved')}</Dialog.Title>
                    <Dialog.Description className={styles.description}>
                        {t('setono_sylius_cms.builder.leave_unsaved_description', { '%reason%': saveStateText(t, failedSave ?? 'failed') })}
                    </Dialog.Description>
                    <div className={styles.actions}>
                        <button type="button" className={styles.secondary} onClick={() => leaving.getState().leaveAnyway()}>
                            {t('setono_sylius_cms.builder.leave_anyway')}
                        </button>
                        <button ref={stay} type="button" className={styles.primary} onClick={() => leaving.getState().stay()}>
                            {t('setono_sylius_cms.builder.stay')}
                        </button>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
