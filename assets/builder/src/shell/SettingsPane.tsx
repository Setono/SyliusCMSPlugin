import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { useBuilder, useBuilderState, useSettingsState, useUrlsState, useValidationState, type BuilderSession } from '../context';
import { fromLocalInput, toLocalInput } from '../dates';
import { FieldRow } from '../form/FieldRow';
import formStyles from '../form/Form.module.css';
import { useTranslator, type MessageKey } from '../i18n';
import { fieldFallback, localeOf, outdatedValue } from '../locales';
import { entryTranslated, entryValues } from '../translationState';
import type { AudienceMode, FieldSchema } from '../types';
import { slugMessages } from '../urls';
import { entryMessages } from '../validation';
import { saveStateText } from './saveStateText';
import styles from './SettingsPane.module.css';

/** A tab of the entry's settings: its label and what its panel shows. */
interface Tab {
    code: string;
    label: MessageKey;
    panel: () => ReactNode;
    /** Whether the entry has the tab. Without it, every entry has it. */
    shown?: (session: BuilderSession) => boolean;
    /** The entry fields the panel shows with their errors, which the errors above the tabs leave out while it's open. */
    fields?: readonly string[];
}

/**
 * The tabs of the entry's settings. The Settings tab holds the edited locale's title and its shown switch, which are
 * drafted, and the on-save settings, which take effect as soon as they're saved, outside the draft. The URL & SEO tab,
 * for a content type with its own URL, holds the edited locale's slug, which is drafted, and its old URLs. The SEO
 * fields join it with their ticket. The More tab links to the overview page.
 */
const tabs: Tab[] = [
    { code: 'settings', label: 'setono_sylius_cms.builder.settings', panel: () => <SettingsTab /> },
    {
        code: 'url_and_seo',
        label: 'setono_sylius_cms.builder.url_and_seo',
        panel: () => <UrlAndSeoTab />,
        shown: ({ urlPrefix }) => urlPrefix !== null,
        fields: ['slug'],
    },
    { code: 'more', label: 'setono_sylius_cms.builder.more', panel: () => <MoreTab /> },
];

/**
 * The entry's settings, shown while no block is selected, in tabs. The arrow keys, Home and End move between the tabs.
 * See "Right pane: the selection" in docs/spec/builder.md
 */
export function SettingsPane() {
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
            <EntryErrors shownElsewhere={tab?.fields ?? []} />
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

function SettingsTab() {
    return (
        <>
            <Title />
            <Shown />
            <Enabled />
            <DateWindow />
            <Channels />
            <Audience />
            <SaveStatus />
        </>
    );
}

function UrlAndSeoTab() {
    return (
        <>
            <Slug />
            <OldUrls />
        </>
    );
}

/**
 * The edited locale's slug, with the URL it gives, and why the URL can't be published or saved: the errors publish
 * would refuse, a slug the server didn't save because another route takes its URL, and warnings, such as another
 * entry that has drafted the URL too. A slug never falls back. See "Slugs" in docs/spec/urls-and-seo.md
 */
function Slug() {
    const t = useTranslator();
    const { store, urlPrefix } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const slug = useBuilderState((state) => state.entry.translations[state.locale]?.fields.slug);
    const refused = useBuilderState((state) => state.refusedSlugs[state.locale]);
    const errors = useValidationState((state) => state.errors);
    const warnings = useUrlsState((state) => state.warnings);
    const url = typeof slug === 'string' && slug !== '' ? `/${locale}/${urlPrefix === null || urlPrefix === '' ? '' : `${urlPrefix}/`}${slug}` : null;
    const warned = slugMessages(warnings, locale);
    const schema: FieldSchema = {
        'x-cms-type': 'text',
        'x-cms-label': t('setono_sylius_cms.builder.slug'),
        'x-cms-help': url === null ? t('setono_sylius_cms.builder.slug_help_none') : t('setono_sylius_cms.builder.slug_help', { '%url%': url }),
        'x-cms-required': true,
        'x-cms-translatable': true,
    };

    return (
        <div className={styles.setting}>
            <FieldRow
                id="cms-entry-slug"
                schema={schema}
                locale={locale}
                value={slug ?? undefined}
                onChange={(value) => store.getState().setEntryValue('slug', value)}
                errors={[...(refused === undefined ? [] : [refused]), ...slugMessages(errors, locale)]}
            />
            {warned.length === 0 ? null : (
                <ul className={styles.warnings}>
                    {warned.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/** The URLs of the edited locale's old slugs, which redirect to its URL. See "Old slugs" in docs/spec/urls-and-seo.md */
function OldUrls() {
    const t = useTranslator();
    const headingId = useId();
    const locale = useBuilderState((state) => state.locale);
    const oldUrls = useUrlsState((state) => state.oldUrls)[locale] ?? [];

    if (oldUrls.length === 0) {
        return null;
    }

    return (
        <div className={styles.setting}>
            <h3 id={headingId} className={formStyles.label}>
                {t('setono_sylius_cms.builder.old_urls')}
            </h3>
            <p className={formStyles.help}>{t('setono_sylius_cms.builder.old_urls_help')}</p>
            <ul className={styles.oldUrls} aria-labelledby={headingId}>
                {oldUrls.map((oldUrl) => (
                    <li key={oldUrl}>
                        <code>{oldUrl}</code>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Points to the entry's overview page, for publishing history, revisions, share links and placements. */
function MoreTab() {
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

/**
 * What publish would refuse in the entry itself: its fields, and what its top level holds, except in the fields the open
 * tab shows with their errors. The entry's fields get forms of their own with their tickets, so each error names its
 * field for now
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

/** The edited locale's title, which shows the title it falls back to while it's empty. Typing one adds a translation. */
function Title() {
    const t = useTranslator();
    const { store, locales, titleChanged } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const translations = useBuilderState((state) => state.entry.translations);
    const titles = entryValues({ translations }, 'title');
    const schema: FieldSchema = {
        'x-cms-type': 'text',
        'x-cms-label': t('setono_sylius_cms.builder.entry_title'),
        'x-cms-help': null,
        'x-cms-required': true,
        'x-cms-translatable': true,
    };

    return (
        <FieldRow
            id="cms-entry-title"
            schema={schema}
            locale={locale}
            value={titles[locale] ?? undefined}
            onChange={(value) => {
                const before = titles[locale];
                store.getState().setEntryValue('title', value);
                titleChanged(locale, typeof before === 'string' ? before : null);
            }}
            fallback={fieldFallback(locales, titles, locale)}
            outdated={outdatedValue(locales, titles, entryTranslated({ translations }, 'title'), locale, () => store.getState().markEntryValueUpToDate('title'))}
        />
    );
}

/**
 * The edited locale's "Show in <language>" switch, which holds the translation back when it's off. It's drafted, so the
 * translation goes live together with its finished text. See "Held-back translations" in docs/spec/publishing.md
 */
function Shown() {
    const t = useTranslator();
    const { store, locales } = useBuilder();
    const locale = localeOf(locales, useBuilderState((state) => state.locale));
    const shown = useBuilderState((state) => state.entry.translations[state.locale]?.shown);
    const hintId = useId();

    if (shown === undefined) {
        return null;
    }

    return (
        <div className={styles.setting}>
            <label className={styles.check}>
                <input type="checkbox" checked={shown} aria-describedby={hintId} onChange={(event) => store.getState().setShown(event.currentTarget.checked)} />
                {t('setono_sylius_cms.builder.show_in_locale', { '%locale%': locale.name })}
            </label>
            <p id={hintId} className={formStyles.help}>
                {t('setono_sylius_cms.builder.show_in_locale_help', { '%locale%': locale.name })}
            </p>
        </div>
    );
}

/** Says beside a setting that it takes effect on save, without publishing. */
function OnSaveHint({ id }: { id: string }) {
    const t = useTranslator();

    return (
        <p id={id} className={formStyles.help}>
            {t('setono_sylius_cms.builder.takes_effect_on_save')}
        </p>
    );
}

function Enabled() {
    const t = useTranslator();
    const { settings } = useBuilder();
    const enabled = useSettingsState((state) => state.settings.enabled);
    const hintId = useId();

    return (
        <div className={styles.setting}>
            <label className={styles.check}>
                <input type="checkbox" checked={enabled} aria-describedby={hintId} onChange={(event) => void settings.getState().save({ enabled: event.currentTarget.checked })} />
                {t('setono_sylius_cms.builder.enabled')}
            </label>
            <OnSaveHint id={hintId} />
        </div>
    );
}

function DateWindow() {
    const t = useTranslator();
    const { settings } = useBuilder();
    const showFrom = useSettingsState((state) => state.settings.showFrom);
    const showUntil = useSettingsState((state) => state.settings.showUntil);
    const hintId = useId();

    return (
        <fieldset className={styles.setting} aria-describedby={hintId}>
            <legend className={formStyles.label}>{t('setono_sylius_cms.builder.date_window')}</legend>
            <DateTimeInput label={t('setono_sylius_cms.builder.show_from')} value={showFrom} onCommit={(value) => void settings.getState().save({ showFrom: value })} />
            <DateTimeInput label={t('setono_sylius_cms.builder.show_until')} value={showUntil} onCommit={(value) => void settings.getState().save({ showUntil: value })} />
            <OnSaveHint id={hintId} />
        </fieldset>
    );
}

/** A date and time in the editor's time zone, saved in UTC when the editor leaves the input. */
function DateTimeInput({ label, value, onCommit }: { label: string; value: string | null; onCommit: (value: string | null) => void }) {
    const id = useId();
    const [local, setLocal] = useState(toLocalInput(value));

    // Shows what's saved, such as after the server refused a change
    useEffect(() => setLocal(toLocalInput(value)), [value]);

    return (
        <div className={styles.field}>
            <label htmlFor={id} className={styles.fieldLabel}>
                {label}
            </label>
            <input
                type="datetime-local"
                id={id}
                className={formStyles.input}
                value={local}
                onChange={(event) => setLocal(event.currentTarget.value)}
                onBlur={() => {
                    const utc = fromLocalInput(local);
                    if (utc !== value) {
                        onCommit(utc);
                    }
                }}
            />
        </div>
    );
}

/** The store's channels to pick the entry's from. A store with one channel has nothing to pick. */
function Channels() {
    const t = useTranslator();
    const { settings, channels } = useBuilder();
    const selected = useSettingsState((state) => state.settings.channels);
    const hintId = useId();

    if (channels.length <= 1) {
        return null;
    }

    return (
        <fieldset className={styles.setting} aria-describedby={hintId}>
            <legend className={formStyles.label}>{t('setono_sylius_cms.builder.channels')}</legend>
            {channels.map(({ code, name }) => (
                <label key={code} className={styles.check}>
                    <input
                        type="checkbox"
                        checked={selected.includes(code)}
                        onChange={(event) => void settings.getState().save({ channels: toggle(selected, code, event.currentTarget.checked) })}
                    />
                    {name}
                </label>
            ))}
            <OnSaveHint id={hintId} />
        </fieldset>
    );
}

const audienceModes: Record<AudienceMode, MessageKey> = {
    everyone: 'setono_sylius_cms.builder.audience_everyone',
    only: 'setono_sylius_cms.builder.audience_only',
    except: 'setono_sylius_cms.builder.audience_except',
};

/** Everyone, only some customer groups, or everyone except some, where guests and customers without a group count as groups. */
function Audience() {
    const t = useTranslator();
    const { settings, customerGroups } = useBuilder();
    const audience = useSettingsState((state) => state.settings.audience);
    const id = useId();
    const hintId = useId();

    // A code no group has any more is a deleted group's. It matches nobody, and can be taken out
    const known = new Set(['@guest', '@no_group', ...customerGroups.map(({ code }) => code)]);
    const groups = [
        { code: '@guest', name: t('setono_sylius_cms.builder.guests') },
        { code: '@no_group', name: t('setono_sylius_cms.builder.no_group') },
        ...customerGroups,
        ...audience.groups.filter((code) => !known.has(code)).map((code) => ({ code, name: `${code} (${t('setono_sylius_cms.builder.deleted')})` })),
    ];

    return (
        <div className={styles.setting}>
            <label htmlFor={id} className={formStyles.label}>
                {t('setono_sylius_cms.builder.audience')}
            </label>
            <select
                id={id}
                className={formStyles.select}
                value={audience.mode}
                aria-describedby={hintId}
                onChange={(event) => {
                    const mode = event.currentTarget.value as AudienceMode;
                    void settings.getState().save({ audience: { mode, groups: mode === 'everyone' ? [] : audience.groups } });
                }}
            >
                {Object.entries(audienceModes).map(([mode, label]) => (
                    <option key={mode} value={mode}>
                        {t(label)}
                    </option>
                ))}
            </select>
            {audience.mode !== 'everyone' && (
                <fieldset className={styles.groups}>
                    <legend className={styles.fieldLabel}>{t('setono_sylius_cms.builder.customer_groups')}</legend>
                    {groups.map(({ code, name }) => (
                        <label key={code} className={styles.check}>
                            <input
                                type="checkbox"
                                checked={audience.groups.includes(code)}
                                onChange={(event) =>
                                    void settings.getState().save({ audience: { mode: audience.mode, groups: toggle(audience.groups, code, event.currentTarget.checked) } })
                                }
                            />
                            {name}
                        </label>
                    ))}
                </fieldset>
            )}
            <OnSaveHint id={hintId} />
        </div>
    );
}

function SaveStatus() {
    const t = useTranslator();
    const saveState = useSettingsState((state) => state.saveState);
    const error = useSettingsState((state) => state.error);

    return (
        <p className={styles.saveState} data-state={saveState} aria-live="polite">
            {saveStateText(t, saveState, error)}
        </p>
    );
}

function toggle(values: string[], value: string, on: boolean): string[] {
    const without = values.filter((item) => item !== value);

    return on ? [...without, value] : without;
}
