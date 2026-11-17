import { useTranslator } from '../i18n';
import type { SearchResult } from '../search/api';
import styles from './CatalogControl.module.css';

/**
 * A product or taxon a field holds, by its thumbnail and name as the search names it: undefined while it's looked up,
 * and null for one the search doesn't find, such as a deleted product, which the shop doesn't show. That one is flagged
 * by its code.
 */
export function CatalogSummary({ code, item }: { code: string; item: SearchResult | null | undefined }) {
    const t = useTranslator();

    if (item === undefined) {
        return <span className={styles.summary} aria-busy="true" />;
    }

    if (item === null) {
        return <span className={`${styles.summary} ${styles.notFound}`}>{t('setono_sylius_cms.builder.items.not_found', { '%code%': code })}</span>;
    }

    return (
        <span className={styles.summary}>
            {item.thumbnail === null ? <span className={styles.thumbnail} aria-hidden="true" /> : <img className={styles.thumbnail} src={item.thumbnail} alt="" />}
            <span className={styles.name}>{item.name}</span>
        </span>
    );
}
