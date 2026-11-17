import * as Popover from '@radix-ui/react-popover';
import { useState } from 'react';
import type { BindablePropertyDefinition } from '../bindings';
import { useTranslator } from '../i18n';
import styles from './Bindings.module.css';

export interface BindingPickerProps {
    /** The properties that fit, in the registry's order. */
    offered: readonly BindablePropertyDefinition[];
    onPick: (property: BindablePropertyDefinition) => void;
    /** The button's accessible name, such as "Bind Heading text". */
    label: string;
    className?: string | undefined;
}

/**
 * The ⚡ button beside a bindable field: it lists the bindable properties of the context the entry provides or the
 * snippet declares, plus the channel's and the customer's, whose type fits the field, and binds the field to the one
 * picked. See "Bindings" in docs/spec/builder.md
 */
export function BindingPicker({ offered, onPick, label, className }: BindingPickerProps) {
    const t = useTranslator();
    const [open, setOpen] = useState(false);

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <button type="button" className={`${styles.bind} ${className ?? ''}`} aria-label={label} title={label}>
                    ⚡
                </button>
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Content className={styles.popover} aria-label={label} side="bottom" align="end" sideOffset={4} collisionPadding={8}>
                    <p className={styles.heading}>{t('setono_sylius_cms.builder.binding.pick')}</p>
                    {offered.length === 0 ? (
                        <p className={styles.none}>{t('setono_sylius_cms.builder.binding.none')}</p>
                    ) : (
                        <ul className={styles.list}>
                            {offered.map((property) => (
                                <li key={property.binding}>
                                    <button
                                        type="button"
                                        className={styles.option}
                                        onClick={() => {
                                            setOpen(false);
                                            onPick(property);
                                        }}
                                    >
                                        {property.label}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}
