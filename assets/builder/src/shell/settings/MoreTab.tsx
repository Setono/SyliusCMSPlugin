import { useId } from 'react';
import type { SnippetPlacement } from '../../config';
import { useBuilder } from '../../context';
import formStyles from '../../form/Form.module.css';
import { useTranslator } from '../../i18n';
import styles from '../SettingsPane.module.css';

/**
 * Points to the entry's overview page, for publishing history, revisions, share links and placements. A snippet's
 * placements are listed here too, without a way to change them: they're managed on the overview page (ADR 0005).
 */
export function MoreTab() {
    const t = useTranslator();
    const { overviewUrl, placements } = useBuilder();

    return (
        <>
            {placements !== null && <Placements placements={placements} />}
            <div className={styles.setting}>
                <p className={formStyles.help}>{t('setono_sylius_cms.builder.more_help')}</p>
                <a href={overviewUrl} className={styles.link}>
                    {t('setono_sylius_cms.builder.open_overview')}
                </a>
            </div>
        </>
    );
}

function Placements({ placements }: { placements: SnippetPlacement[] }) {
    const t = useTranslator();
    const headingId = useId();

    return (
        <div className={styles.setting}>
            <h3 id={headingId} className={formStyles.label}>
                {t('setono_sylius_cms.builder.placements')}
            </h3>
            {placements.length === 0 ? (
                <p className={formStyles.help}>{t('setono_sylius_cms.builder.no_placements')}</p>
            ) : (
                <>
                    <ul className={styles.placements} aria-labelledby={headingId}>
                        {placements.map((placement) => (
                            <li key={placement.uuid} title={placement.slot}>
                                {placement.removed ? t('setono_sylius_cms.builder.placement_removed', { '%slot%': placement.slot }) : placement.label}
                            </li>
                        ))}
                    </ul>
                    <p className={formStyles.help}>{t('setono_sylius_cms.builder.placements_help')}</p>
                </>
            )}
        </div>
    );
}
