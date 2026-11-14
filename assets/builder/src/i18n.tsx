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
    | 'setono_sylius_cms.builder.blocks'
    | 'setono_sylius_cms.builder.add_block'
    | 'setono_sylius_cms.builder.delete_block'
    | 'setono_sylius_cms.builder.no_blocks'
    | 'setono_sylius_cms.builder.no_selection'
    | 'setono_sylius_cms.builder.unsupported_field_type';

export type Translate = (key: MessageKey) => string;

/** Looks a key up in the strings, falling back to the key itself. */
export function createTranslator(strings: Readonly<Record<string, string>>): Translate {
    return (key) => strings[key] ?? key;
}

const TranslatorContext = createContext<Translate>((key) => key);

export const TranslatorProvider = TranslatorContext.Provider;

export function useTranslator(): Translate {
    return useContext(TranslatorContext);
}
