import { useBuilder } from '../../context';
import formStyles from '../../form/Form.module.css';
import { useTranslator } from '../../i18n';
import styles from '../SettingsPane.module.css';

/** Points to the entry's overview page, for publishing history, revisions, share links and placements. */
export function MoreTab() {
    const t = useTranslator();
    const { overviewUrl } = useBuilder();

    return (
        <div className={styles.setting}>
            <p className={formStyles.help}>{t('setono_sylius_cms.builder.more_help')}</p>
            <a href={overviewUrl} className={styles.link}>
                {t('setono_sylius_cms.builder.open_overview')}
            </a>
        </div>
    );
}
