import { createContext, useContext } from 'react';

/** The UI strings the builder uses. They come translated from the registry, keyed by translation key. */
export type MessageKey =
    | 'setono_sylius_cms.builder.loading'
    | 'setono_sylius_cms.builder.load_failed'
    | 'setono_sylius_cms.builder.back'
    | 'setono_sylius_cms.builder.saving'
    | 'setono_sylius_cms.builder.saved'
    | 'setono_sylius_cms.builder.save_failed'
    | 'setono_sylius_cms.builder.publish'
    | 'setono_sylius_cms.builder.publishing'
    | 'setono_sylius_cms.builder.published'
    | 'setono_sylius_cms.builder.never_published'
    | 'setono_sylius_cms.builder.publish_failed'
    | 'setono_sylius_cms.builder.unpublished_changes'
    | 'setono_sylius_cms.builder.discard_changes'
    | 'setono_sylius_cms.builder.discarding'
    | 'setono_sylius_cms.builder.discard_failed'
    | 'setono_sylius_cms.builder.blocks'
    | 'setono_sylius_cms.builder.add_block'
    | 'setono_sylius_cms.builder.add_block_inside'
    | 'setono_sylius_cms.builder.add_block_above'
    | 'setono_sylius_cms.builder.add_block_below'
    | 'setono_sylius_cms.builder.select_parent'
    | 'setono_sylius_cms.builder.block_settings'
    | 'setono_sylius_cms.builder.search_block_types'
    | 'setono_sylius_cms.builder.no_matching_block_types'
    | 'setono_sylius_cms.builder.drop_refused'
    | 'setono_sylius_cms.builder.children'
    | 'setono_sylius_cms.builder.delete_block'
    | 'setono_sylius_cms.builder.duplicate'
    | 'setono_sylius_cms.builder.move_up'
    | 'setono_sylius_cms.builder.move_down'
    | 'setono_sylius_cms.builder.undo'
    | 'setono_sylius_cms.builder.redo'
    | 'setono_sylius_cms.builder.path'
    | 'setono_sylius_cms.builder.blocks_selected'
    | 'setono_sylius_cms.builder.no_blocks'
    | 'setono_sylius_cms.builder.no_selection'
    | 'setono_sylius_cms.builder.unsupported_field_type'
    | 'setono_sylius_cms.builder.preview'
    | 'setono_sylius_cms.builder.preview_failed'
    | 'setono_sylius_cms.builder.preview_channel'
    | 'setono_sylius_cms.builder.preview_width'
    | 'setono_sylius_cms.builder.desktop'
    | 'setono_sylius_cms.builder.mobile';

/** Values for the placeholders in a string, such as { '%block%': 'Columns' }. */
export type TranslationParameters = Readonly<Record<string, string>>;

export type Translate = (key: MessageKey, parameters?: TranslationParameters) => string;

/** Looks a key up in the strings, falling back to the key itself, and fills in its placeholders. */
export function createTranslator(strings: Readonly<Record<string, string>>): Translate {
    return (key, parameters = {}) => Object.entries(parameters).reduce((text, [placeholder, value]) => text.replaceAll(placeholder, value), strings[key] ?? key);
}

const TranslatorContext = createContext<Translate>((key) => key);

export const TranslatorProvider = TranslatorContext.Provider;

export function useTranslator(): Translate {
    return useContext(TranslatorContext);
}
