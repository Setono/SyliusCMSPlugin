/**
 * The entry's on-save settings: enabled, the date window, the channels and the audience. They take effect as soon as
 * they're saved, outside the draft, so they're kept apart from the working copy's store: changing one is no edit to
 * the draft, it's never undone, and it's saved through its own endpoint at once instead of by autosave.
 */
import { createStore, type StoreApi } from 'zustand/vanilla';
import { ApiError } from './api';
import type { SaveState } from './store';
import type { EntrySettings } from './types';

/** idle until the first change, then saving while saves are in flight, then saved, or failed. Settings never go stale. */
export type SettingsSaveState = Exclude<SaveState, 'stale'>;

export interface SettingsState {
    settings: EntrySettings;
    saveState: SettingsSaveState;
    /** Why the server refused a save, such as a date window that ends before it starts, or null. */
    error: string | null;
    /** Shows the changes at once and saves them, after any save in flight. */
    save(changes: Partial<EntrySettings>): Promise<void>;
    /** Settles once the saves in flight have, whether they went through or not. */
    settled(): Promise<void>;
}

export type SettingsStore = StoreApi<SettingsState>;

export function createSettingsStore(initial: EntrySettings, send: (changes: Partial<EntrySettings>) => Promise<EntrySettings>): SettingsStore {
    /** What the server last said it has. */
    let saved = initial;
    let queue: Promise<void> = Promise.resolve();
    let pending = 0;
    /** Set when a save failed since the saves in flight started, with the server's reason or null. */
    let failure: { error: string | null } | null = null;

    return createStore<SettingsState>()((set, get) => ({
        settings: initial,
        saveState: 'idle',
        error: null,

        save(changes) {
            pending++;
            set({ settings: { ...get().settings, ...changes }, saveState: 'saving', error: null });

            const run = async (): Promise<void> => {
                try {
                    saved = await send(changes);
                } catch (error) {
                    failure = { error: error instanceof ApiError ? error.message : null };
                }

                // The last save in flight shows what the server has, which holds every save that went through.
                if (--pending === 0) {
                    set({ settings: saved, saveState: failure === null ? 'saved' : 'failed', error: failure?.error ?? null });
                    failure = null;
                }
            };

            // The server applies saves in the order they're sent, so a later save waits for the one in flight.
            queue = pending === 1 ? run() : queue.then(run);

            return queue;
        },

        // A save that fails is caught where it runs, so the queue never rejects
        settled: () => queue,
    }));
}
