import { useEffect, useId, useState } from 'react';
import { useBuilder, useBuilderState, useSettingsState, useValidationState } from '../context';
import { fromLocalInput, toLocalInput } from '../dates';
import formStyles from '../form/Form.module.css';
import { useTranslator, type MessageKey } from '../i18n';
import type { AudienceMode } from '../types';
import { entryMessages } from '../validation';
import styles from './SettingsPane.module.css';

/**
 * The entry's settings, shown while no block is selected. The Settings tab holds the on-save settings, which take
 * effect as soon as they're saved, outside the draft. The title, the layout, the URL & SEO tab and the More tab join it
 * with their tickets. See "Right pane: the selection" in docs/spec/builder.md
 */
export function SettingsPane() {
    const t = useTranslator();
    const tabId = useId();
    const panelId = useId();

    return (
        <div className={styles.pane}>
            <EntryErrors />
            <div role="tablist" className={styles.tabs} aria-label={t('setono_sylius_cms.builder.entry_settings')}>
                <button type="button" role="tab" id={tabId} className={styles.tab} aria-selected="true" aria-controls={panelId}>
                    {t('setono_sylius_cms.builder.settings')}
                </button>
            </div>
            <div role="tabpanel" id={panelId} className={styles.panel} aria-labelledby={tabId}>
                <Enabled />
                <DateWindow />
                <Channels />
                <Audience />
                <SaveState />
            </div>
        </div>
    );
}

/**
 * What publish would refuse in the entry itself: its fields, and what its top level holds. The entry's fields get
 * forms of their own with their tickets, so each error names its field for now
 */
function EntryErrors() {
    const errors = useValidationState((state) => state.errors);
    const locale = useBuilderState((state) => state.locale);
    const messages = entryMessages(errors, locale);

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

function SaveState() {
    const t = useTranslator();
    const saveState = useSettingsState((state) => state.saveState);
    const error = useSettingsState((state) => state.error);
    const failed = t('setono_sylius_cms.builder.save_failed');
    const text = {
        idle: '',
        saving: t('setono_sylius_cms.builder.saving'),
        saved: t('setono_sylius_cms.builder.saved'),
        failed: error === null ? failed : `${failed}: ${error}`,
    }[saveState];

    return (
        <p className={styles.saveState} data-state={saveState} aria-live="polite">
            {text}
        </p>
    );
}

function toggle(values: string[], value: string, on: boolean): string[] {
    const without = values.filter((item) => item !== value);

    return on ? [...without, value] : without;
}
