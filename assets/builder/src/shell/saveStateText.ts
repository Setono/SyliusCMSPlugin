import type { Translate } from '../i18n';
import type { SaveState } from '../store';

/** What a save state says, with the server's reason after a failed save when it gave one. */
export function saveStateText(t: Translate, state: SaveState, error: string | null = null): string {
    const failed = t('setono_sylius_cms.builder.save_failed');

    return {
        idle: '',
        saving: t('setono_sylius_cms.builder.saving'),
        saved: t('setono_sylius_cms.builder.saved'),
        failed: error === null ? failed : `${failed}: ${error}`,
        stale: t('setono_sylius_cms.builder.save_stale'),
        locked: t('setono_sylius_cms.builder.save_locked'),
    }[state];
}
