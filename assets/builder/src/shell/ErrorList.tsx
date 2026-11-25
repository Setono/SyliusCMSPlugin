import styles from './ErrorList.module.css';

/**
 * What publish would refuse somewhere, such as in a block as a whole or in one of its conditions: its messages, or
 * nothing while there are none.
 */
export function ErrorList({ messages }: { messages: readonly string[] }) {
    if (messages.length === 0) {
        return null;
    }

    return (
        <ul className={styles.errors}>
            {messages.map((message) => (
                <li key={message}>{message}</li>
            ))}
        </ul>
    );
}
