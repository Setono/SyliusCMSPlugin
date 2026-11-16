import { useTranslator } from '../i18n';
import styles from './Form.module.css';

/** Shown in place of the control of a field type the builder doesn't know. */
export function UnsupportedControl({ id }: { id: string }) {
    const t = useTranslator();

    return (
        <p id={id} className={styles.unsupported}>
            {t('setono_sylius_cms.builder.unsupported_field_type')}
        </p>
    );
}
