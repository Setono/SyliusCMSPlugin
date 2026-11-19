import { useState } from 'react';
import { useBuilder } from '../context';
import { useTranslator } from '../i18n';
import styles from './NoticesBanner.module.css';

/**
 * What the server tells the editor once the builder opens, such as the blocks of a disabled type that creating the
 * entry from a starting point left out, as inserting a preset tells it. The editor dismisses it. See "Starting points"
 * in docs/spec/layouts-and-presets.md
 */
export function NoticesBanner() {
    const t = useTranslator();
    const { notices } = useBuilder();
    const [dismissed, setDismissed] = useState(false);

    if (dismissed || notices.length === 0) {
        return null;
    }

    return (
        <div className={styles.banner} role="status">
            <p className={styles.message}>{notices.join(' ')}</p>
            <button type="button" className={styles.dismiss} onClick={() => setDismissed(true)}>
                {t('setono_sylius_cms.builder.dismiss')}
            </button>
        </div>
    );
}
