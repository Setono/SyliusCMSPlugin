import { useId, useState } from 'react';
import { translatedValues } from '../../bindings';
import { useBuilder, useBuilderState, useSettingsState } from '../../context';
import { FieldRow } from '../../form/FieldRow';
import formStyles from '../../form/Form.module.css';
import { LocalDateTimeInput } from '../../form/LocalDateTimeInput';
import { CONTEXT_FIELD, contextBlockerMessage, contextBlockers, contextChoices, contextNames, declaredContext, sameContext } from '../../hostPageContext';
import { useTranslator, type MessageKey } from '../../i18n';
import { isRemovedLayout, layoutOf, pickableLayouts, renderedLayout } from '../../layouts';
import { fieldFallback, localeOf, outdatedValue } from '../../locales';
import { locks } from '../../store';
import { entryTranslated, entryValues } from '../../translationState';
import type { AudienceMode, FieldSchema } from '../../types';
import { readFieldValue } from '../../values';
import { saveStateText } from '../saveStateText';
import styles from '../SettingsPane.module.css';

export function SettingsTab() {
    // A scheduled publish locks what's drafted, but not the on-save settings, which aren't part of the draft
    const locked = useBuilderState((state) => locks(state.schedule));

    return (
        <>
            <fieldset className={styles.locked} disabled={locked}>
                <Title />
                <Shown />
                <Layout />
                <HostPageContext />
            </fieldset>
            <Enabled />
            <DateWindow />
            <Channels />
            <Audience />
            <HomepageOf />
            <SaveStatus />
        </>
    );
}

/**
 * The edited locale's title, which shows the title it falls back to while it's empty. Typing one adds a translation.
 * It says so when the layout doesn't show it.
 */
function Title() {
    const t = useTranslator();
    const { store, locales, titleChanged } = useBuilder();
    const locale = useBuilderState((state) => state.locale);
    const translations = useBuilderState((state) => state.entry.translations);
    const shown = useShownByLayout('title');
    const titles = entryValues({ translations }, 'title');
    const schema: FieldSchema = {
        'x-cms-type': 'text',
        'x-cms-label': t('setono_sylius_cms.builder.entry_title'),
        'x-cms-help': shown ? null : t('setono_sylius_cms.builder.not_shown_in_layout'),
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
 * Whether the layout the entry renders in prints an entry field. Without layouts, as for a content type without its own
 * URL, there's no layout to leave it out.
 */
export function useShownByLayout(field: string): boolean {
    const layouts = useBuilderState((state) => state.layouts);
    const stored = useBuilderState((state) => state.entry.tree.layout);
    const rendered = renderedLayout(layouts, stored);

    return rendered === undefined || rendered.shows.includes(field);
}

/**
 * The entry's layout: a card per layout to pick from, when there's more than one, the fields of the layout it's in, and a
 * warning when its layout was removed from code or disabled, until another is picked. Switching layout and the layout's
 * fields are drafted. See "Picking and switching" in docs/spec/layouts-and-presets.md
 */
function Layout() {
    const t = useTranslator();
    const { store, locales } = useBuilder();
    const layouts = useBuilderState((state) => state.layouts);
    const stored = useBuilderState((state) => state.entry.tree.layout);
    const locale = useBuilderState((state) => state.locale);
    const name = useId();

    if (layouts.length === 0) {
        return null;
    }

    const pickable = pickableLayouts(layouts);
    const removed = isRemovedLayout(layouts, stored);
    const rendered = renderedLayout(layouts, stored);
    // The fields of a layout that was removed or disabled aren't shown. A tree without a layout renders in the default
    // one, so its fields are, and a first value stores it
    const own = removed ? undefined : stored === null ? rendered : layoutOf(layouts, stored);
    const values = stored?.fields ?? {};
    const translated = stored?.translated ?? {};

    return (
        <>
            {removed && stored !== null && (
                <p className={styles.warning} role="status">
                    {t('setono_sylius_cms.builder.layout_removed', { '%layout%': stored.code, '%default%': rendered?.label ?? '' })}
                </p>
            )}
            {(pickable.length > 1 || removed) && (
                <fieldset className={styles.setting}>
                    <legend className={formStyles.label}>{t('setono_sylius_cms.builder.layout')}</legend>
                    <div className={styles.layouts}>
                        {pickable.map((layout) => (
                            <label key={layout.code} className={styles.layout}>
                                <input
                                    type="radio"
                                    className={styles.layoutRadio}
                                    name={name}
                                    value={layout.code}
                                    checked={!removed && rendered?.code === layout.code}
                                    onChange={() => store.getState().setLayout(layout.code)}
                                />
                                {layout.thumbnail === null ? (
                                    <span className={styles.layoutThumbnail} />
                                ) : (
                                    <img className={styles.layoutThumbnail} src={layout.thumbnail} alt="" />
                                )}
                                <span className={styles.layoutLabel}>{layout.label}</span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}
            {own !== undefined && own.fields.length > 0 && (
                <div key={own.code} className={styles.layoutFields}>
                    {own.fields.map(({ name: field, schema }) => (
                        <FieldRow
                            key={field}
                            id={`cms-layout-field-${field}`}
                            schema={schema}
                            locale={locale}
                            value={readFieldValue(values, field, schema['x-cms-translatable'], locale)}
                            onChange={(value) => store.getState().setLayoutValue(field, value)}
                            // A bound field's fallback is translated like its value
                            fallback={schema['x-cms-translatable'] ? fieldFallback(locales, translatedValues(values[field]), locale) : undefined}
                            binding={{ stored: values[field], set: (value) => store.getState().setLayoutBinding(field, value) }}
                            outdated={
                                schema['x-cms-translatable']
                                    ? outdatedValue(locales, values[field], translated[field], locale, () => store.getState().markLayoutValueUpToDate(field))
                                    : undefined
                            }
                        />
                    ))}
                </div>
            )}
        </>
    );
}

/**
 * A snippet's host-page context, which it declares: the pages it can be placed on, such as "Product page", or "Any page",
 * which decides the block types and condition kinds offered. It can't change while something depends on it, so a
 * change something is in the way of isn't made, and what's in the way is listed, as the server lists it when it refuses
 * one. It's drafted. See "Snippets and their context" in docs/spec/slots.md
 */
function HostPageContext() {
    const t = useTranslator();
    const { store, placements } = useBuilder();
    const rules = useBuilderState((state) => state.rules);
    const entry = useBuilderState((state) => state.entry);
    const contextKeys = useBuilderState((state) => state.contextKeys);
    const slots = useBuilderState((state) => state.slots);
    const blockTypes = useBuilderState((state) => state.blockTypes);
    const conditionKinds = useBuilderState((state) => state.conditionKinds);
    const refused = useBuilderState((state) => state.refusedContext);
    const [inTheWay, setInTheWay] = useState<string[]>([]);
    const id = useId();

    // Only a snippet declares its context: an entry of any other content type provides itself
    if (rules.provides !== null) {
        return null;
    }

    const declared = declaredContext(entry);
    const choices = contextChoices(contextKeys, slots, declared);
    const current = choices.find((choice) => sameContext(choice, declared)) ?? declared;
    const shown = inTheWay.length > 0 ? inTheWay : refused;

    const pick = (keys: string[]): void => {
        const blockers = contextBlockers(declared, keys, {
            blocks: entry.tree.blocks,
            blockTypes: new Map(blockTypes.map((blockType) => [blockType.code, blockType])),
            conditionKinds,
            placements: placements ?? [],
            slots,
        });
        setInTheWay(blockers.map((blocker) => contextBlockerMessage(blocker, contextKeys, t)));
        if (blockers.length === 0) {
            store.getState().setSharedEntryValue(CONTEXT_FIELD, keys.length === 0 ? null : keys);
        }
    };

    return (
        <div className={styles.setting}>
            <label htmlFor={id} className={formStyles.label}>
                {t('setono_sylius_cms.builder.context')}
            </label>
            <select
                id={id}
                className={formStyles.select}
                value={current.join(',')}
                aria-describedby={`${id}-help`}
                onChange={(event) => pick(choices.find((choice) => choice.join(',') === event.currentTarget.value) ?? [])}
            >
                {choices.map((choice) => (
                    <option key={choice.join(',')} value={choice.join(',')}>
                        {choice.length === 0 ? t('setono_sylius_cms.builder.context_any_page') : contextNames(choice, contextKeys, t).join(', ')}
                    </option>
                ))}
            </select>
            <p id={`${id}-help`} className={formStyles.help}>
                {t('setono_sylius_cms.builder.context_help')}
            </p>
            {shown.length > 0 && (
                <div role="alert" className={styles.warning}>
                    <p className={styles.inTheWay}>{t('setono_sylius_cms.builder.context_in_the_way')}</p>
                    <ul className={styles.inTheWayList}>
                        {shown.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
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

    return (
        <div className={styles.field}>
            <label htmlFor={id} className={styles.fieldLabel}>
                {label}
            </label>
            <LocalDateTimeInput id={id} className={formStyles.input} value={value} commitOn="blur" onCommit={onCommit} />
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

/**
 * "Homepage of", for a page: the store's channels it shows at the root URL of, in place of the store's own homepage. A
 * channel has one homepage, so choosing it here takes it off any other page. Unlike the channel picker it shows in a
 * store with one channel too. See "Homepage" in docs/spec/urls-and-seo.md
 */
function HomepageOf() {
    const t = useTranslator();
    const { settings, channels, homepage } = useBuilder();
    const selected = useSettingsState((state) => state.settings.homepageOf);
    const helpId = useId();
    const hintId = useId();

    if (!homepage) {
        return null;
    }

    return (
        <fieldset className={styles.setting} aria-describedby={`${helpId} ${hintId}`}>
            <legend className={formStyles.label}>{t('setono_sylius_cms.builder.homepage_of')}</legend>
            {channels.map(({ code, name }) => (
                <label key={code} className={styles.check}>
                    <input
                        type="checkbox"
                        checked={selected.includes(code)}
                        onChange={(event) => void settings.getState().save({ homepageOf: toggle(selected, code, event.currentTarget.checked) })}
                    />
                    {name}
                </label>
            ))}
            <p id={helpId} className={formStyles.help}>
                {t('setono_sylius_cms.builder.homepage_of_help')}
            </p>
            <OnSaveHint id={hintId} />
        </fieldset>
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
