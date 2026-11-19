import { useId, useRef, useState, type ReactNode } from 'react';
import { useBuilder, useBuilderState, useValidationState, type BuilderSession } from '../context';
import { useTranslator, type MessageKey } from '../i18n';
import { entryMessages } from '../validation';
import { StarIcon } from './icons';
import { MoreTab } from './settings/MoreTab';
import { SettingsTab } from './settings/SettingsTab';
import { UrlAndSeoTab } from './settings/UrlAndSeoTab';
import styles from './SettingsPane.module.css';

/** A tab of the entry's settings: its label and what its panel shows. */
interface Tab {
    code: string;
    label: MessageKey;
    panel: () => ReactNode;
    /** Whether the entry has the tab. Without it, every entry has it. */
    shown?: (session: BuilderSession) => boolean;
    /** The entry fields the panel shows with their errors, which the errors above the tabs leave out while it's open. */
    fields?: (session: BuilderSession) => readonly string[];
}

/**
 * The tabs of the entry's settings. The Settings tab holds the edited locale's title and its shown switch, the content
 * type's own fields, and the layout with its fields, which are drafted, and the on-save settings, which take effect as soon as they're saved,
 * outside the draft. The URL & SEO tab, for a content type with its own URL, holds the edited locale's slug, its old
 * URLs and the SEO fields, which are drafted. The More tab links to the overview page.
 */
const tabs: Tab[] = [
    {
        code: 'settings',
        label: 'setono_sylius_cms.builder.settings',
        panel: () => <SettingsTab />,
        fields: ({ entryFields }) => entryFields.map(({ name }) => name),
    },
    {
        code: 'url_and_seo',
        label: 'setono_sylius_cms.builder.url_and_seo',
        panel: () => <UrlAndSeoTab />,
        shown: ({ urlPrefix }) => urlPrefix !== null,
        fields: () => ['slug', 'metaTitle', 'metaDescription', 'noindex'],
    },
    { code: 'more', label: 'setono_sylius_cms.builder.more', panel: () => <MoreTab /> },
];

export interface SettingsPaneProps {
    /** Opens Save as starting point for the whole tree. Without it, as for a content type without a block tree, there's none. */
    onSaveStartingPoint?: (() => void) | undefined;
}

/**
 * The entry's settings, shown while no block is selected, in tabs, under the action that saves the whole tree as a
 * starting point. The arrow keys, Home and End move between the tabs. See "Right pane: the selection" in
 * docs/spec/builder.md and "Editor presets" in docs/spec/layouts-and-presets.md
 */
export function SettingsPane({ onSaveStartingPoint }: SettingsPaneProps = {}) {
    const t = useTranslator();
    const id = useId();
    const session = useBuilder();
    const [selected, setSelected] = useState(0);
    const tabList = useRef<HTMLDivElement>(null);
    const shownTabs = tabs.filter(({ shown }) => shown?.(session) ?? true);
    const tab = shownTabs[selected] ?? shownTabs[0];

    const select = (index: number): void => {
        const next = (index + shownTabs.length) % shownTabs.length;
        setSelected(next);
        tabList.current?.querySelectorAll<HTMLElement>('[role="tab"]')[next]?.focus();
    };

    const keys: Record<string, () => void> = {
        ArrowRight: () => select(selected + 1),
        ArrowLeft: () => select(selected - 1),
        Home: () => select(0),
        End: () => select(shownTabs.length - 1),
    };

    return (
        <div className={styles.pane}>
            {onSaveStartingPoint !== undefined && <SaveStartingPoint onSave={onSaveStartingPoint} />}
            <EntryErrors shownElsewhere={tab?.fields?.(session) ?? []} />
            <div
                ref={tabList}
                role="tablist"
                className={styles.tabs}
                aria-label={t('setono_sylius_cms.builder.entry_settings')}
                onKeyDown={(event) => {
                    const key = keys[event.key];
                    if (key !== undefined) {
                        event.preventDefault();
                        key();
                    }
                }}
            >
                {shownTabs.map(({ code, label }, index) => (
                    <button
                        key={code}
                        type="button"
                        role="tab"
                        id={`${id}-${code}-tab`}
                        className={styles.tab}
                        aria-selected={index === selected}
                        aria-controls={`${id}-${code}-panel`}
                        tabIndex={index === selected ? 0 : -1}
                        onClick={() => setSelected(index)}
                    >
                        {t(label)}
                    </button>
                ))}
            </div>
            {tab !== undefined && (
                <div role="tabpanel" id={`${id}-${tab.code}-panel`} className={styles.panel} aria-labelledby={`${id}-${tab.code}-tab`}>
                    {tab.panel()}
                </div>
            )}
        </div>
    );
}

/**
 * ★ Save as starting point: the whole tree, with its layout and the layout's field values, as a starting point for new
 * entries of the content type. A tree without blocks has nothing to save.
 */
function SaveStartingPoint({ onSave }: { onSave: () => void }) {
    const t = useTranslator();
    const hintId = useId();
    const empty = useBuilderState((state) => state.entry.tree.blocks.length === 0);

    return (
        <div className={styles.paneActions}>
            <button type="button" className={styles.paneAction} disabled={empty} aria-describedby={empty ? hintId : undefined} onClick={onSave}>
                <StarIcon />
                {t('setono_sylius_cms.builder.save_as_starting_point')}
            </button>
            {empty && (
                <p id={hintId} className={styles.paneActionHint}>
                    {t('setono_sylius_cms.builder.save_as_starting_point_empty')}
                </p>
            )}
        </div>
    );
}

/**
 * What publish would refuse in the entry itself: its fields, and what its top level holds, except in the fields the open
 * tab shows with their errors, so each one names its field
 */
function EntryErrors({ shownElsewhere }: { shownElsewhere: readonly string[] }) {
    const errors = useValidationState((state) => state.errors);
    const locale = useBuilderState((state) => state.locale);
    const messages = entryMessages(
        errors.filter(({ field }) => field === null || !shownElsewhere.includes(field)),
        locale,
    );

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
