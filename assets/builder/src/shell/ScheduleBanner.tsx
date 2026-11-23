import { useBuilder, useBuilderState } from '../context';
import { formatDateTime } from '../dates';
import { useTranslator } from '../i18n';
import styles from './ScheduleBanner.module.css';

/**
 * Says when the entry has a scheduled publish, in the time zone the server gives, which it names: one that waits locks
 * the working copy, so the builder is read-only until the editor cancels it in the top bar; one that's overdue says the
 * Messenger worker may not be running; and one that was stopped when it ran says why. See "Scheduled publishes" in
 * docs/spec/publishing.md
 */
export function ScheduleBanner() {
    const t = useTranslator();
    const { timeZone } = useBuilder();
    const schedule = useBuilderState((state) => state.schedule);

    if (schedule === null) {
        return null;
    }

    const at = { '%at%': formatDateTime(schedule.at, timeZone), '%time_zone%': timeZone };
    const message = {
        publishes_at_a_time: () => t('setono_sylius_cms.builder.locked', at),
        scheduled_publish_overdue: () => t('setono_sylius_cms.builder.locked_overdue', at),
        scheduled_publish_failed: () => t('setono_sylius_cms.builder.schedule_stopped', { ...at, '%reason%': schedule.reason ?? '' }),
    }[schedule.status];

    return (
        <div className={styles.banner} data-schedule={schedule.status}>
            <p className={styles.message}>{message()}</p>
        </div>
    );
}
