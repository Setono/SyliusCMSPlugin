import type { Editor } from '@tiptap/core';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { LinkTargetDefinition } from '../src/links';
import { LinkTargetsProvider } from '../src/linkTargets';
import type { SearchResult } from '../src/search/api';
import type { JsonObject, JsonValue } from '../src/types';
import { fakeSearch, renderField, richTextSchema, strings as builderStrings } from './fixtures';

const strings = {
    ...builderStrings,
    'setono_sylius_cms.builder.link.anchor': 'Anchor',
    'setono_sylius_cms.builder.link.change': 'Change…',
    'setono_sylius_cms.builder.link.choose': 'Choose…',
    'setono_sylius_cms.builder.link.nothing_picked': 'Nothing chosen',
    'setono_sylius_cms.builder.link.search': 'Search: %target%',
    'setono_sylius_cms.builder.link.url': 'URL',
    'setono_sylius_cms.builder.rich_text.link': 'Link',
    'setono_sylius_cms.builder.rich_text.link_target': 'Links to',
    'setono_sylius_cms.builder.rich_text.link_apply': 'Apply',
    'setono_sylius_cms.builder.rich_text.link_remove': 'Remove link',
    'setono_sylius_cms.builder.rich_text.link_cancel': 'Cancel',
    'setono_sylius_cms.builder.search.searching': 'Searching…',
    'setono_sylius_cms.builder.search.no_results': 'Nothing found',
    'setono_sylius_cms.builder.search.failed': 'The search failed. Try again.',
};

const linkTargets: LinkTargetDefinition[] = [
    { code: 'entry', label: 'Entry', translatableRef: false },
    { code: 'product', label: 'Product', translatableRef: false },
    { code: 'url', label: 'URL', translatableRef: true },
];

const STORY = '0193a5be-0000-7000-8000-000000000001';

const entries: SearchResult[] = [
    { ref: STORY, name: 'Our story', thumbnail: null },
    { ref: '0193a5be-0000-7000-8000-000000000002', name: 'Our team', thumbnail: null },
];

function renderRichText(initial: JsonValue) {
    const { onChange } = renderField(richTextSchema, {
        initial,
        strings,
        search: fakeSearch({ entry: entries }),
        providers: (field) => <LinkTargetsProvider value={linkTargets}>{field}</LinkTargetsProvider>,
    });

    return { onChange, editor: screen.getByRole('textbox', { name: 'Content' }) };
}

/** Selects the text between two positions of the document, as the editor does with the mouse. */
function select(element: HTMLElement, from: number, to: number = from): void {
    act(() => {
        (element as HTMLElement & { editor: Editor }).editor.commands.setTextSelection({ from, to });
    });
}

function doc(...content: JsonObject[]): JsonObject {
    return { type: 'doc', content: [{ type: 'paragraph', content }] };
}

function text(value: string, link?: { target: string; ref: string; anchor: string | null }): JsonObject {
    return link === undefined ? { type: 'text', text: value } : { type: 'text', text: value, marks: [{ type: 'cmsLink', attrs: link }] };
}

function linkButton(): HTMLElement {
    return within(screen.getByRole('toolbar', { name: 'Formatting' })).getByRole('button', { name: 'Link' });
}

describe('links in rich text', () => {
    it('should link the selected words to an entry picked with the search, with the target picker a Link field has', async () => {
        const { onChange, editor } = renderRichText(doc(text('Read our story')));
        expect(linkButton()).toBeDisabled();

        // "our story"
        select(editor, 6, 15);
        expect(linkButton()).toBeEnabled();
        expect(linkButton()).toHaveAttribute('aria-pressed', 'false');
        fireEvent.click(linkButton());

        const panel = screen.getByRole('group', { name: 'Link' });
        expect(within(panel).getByRole('combobox', { name: 'Links to' })).toHaveValue('entry');
        expect(within(panel).getByRole('button', { name: 'Apply' })).toBeDisabled();
        fireEvent.click(within(panel).getByRole('button', { name: 'Choose…' }));
        fireEvent.change(await screen.findByRole('combobox', { name: 'Search: Entry' }), { target: { value: 'story' } });
        fireEvent.click(await screen.findByRole('option', { name: 'Our story' }));
        await waitFor(() => expect(within(panel).getByText('Our story')).toBeInTheDocument());

        fireEvent.click(within(panel).getByRole('button', { name: 'Apply' }));

        expect(onChange).toHaveBeenLastCalledWith(doc(text('Read '), text('our story', { target: 'entry', ref: STORY, anchor: null })));
        expect(screen.queryByRole('group', { name: 'Link' })).not.toBeInTheDocument();
        expect(linkButton()).toHaveAttribute('aria-pressed', 'true');
    });

    it('should link the selected words to a URL typed with an anchor', () => {
        const { onChange, editor } = renderRichText(doc(text('Read our story')));
        select(editor, 1, 5);
        fireEvent.click(linkButton());

        const panel = screen.getByRole('group', { name: 'Link' });
        fireEvent.change(within(panel).getByRole('combobox', { name: 'Links to' }), { target: { value: 'url' } });
        fireEvent.change(within(panel).getByRole('textbox', { name: 'URL' }), { target: { value: 'https://example.com/about' } });
        fireEvent.change(within(panel).getByRole('textbox', { name: 'Anchor' }), { target: { value: '#team' } });
        fireEvent.click(within(panel).getByRole('button', { name: 'Apply' }));

        expect(onChange).toHaveBeenLastCalledWith(doc(text('Read', { target: 'url', ref: 'https://example.com/about', anchor: 'team' }), text(' our story')));
    });

    it('should show the link the cursor is in, change it as a whole and remove it', async () => {
        const link = { target: 'entry', ref: STORY, anchor: null };
        const { onChange, editor } = renderRichText(doc(text('Read '), text('our story', link)));

        // The cursor inside "our story", with nothing selected
        select(editor, 9);
        expect(linkButton()).toHaveAttribute('aria-pressed', 'true');
        fireEvent.click(linkButton());

        const panel = screen.getByRole('group', { name: 'Link' });
        expect(within(panel).getByRole('combobox', { name: 'Links to' })).toHaveValue('entry');
        expect(await within(panel).findByText('Our story')).toBeInTheDocument();
        fireEvent.change(within(panel).getByRole('textbox', { name: 'Anchor' }), { target: { value: 'history' } });
        fireEvent.click(within(panel).getByRole('button', { name: 'Apply' }));

        expect(onChange).toHaveBeenLastCalledWith(doc(text('Read '), text('our story', { ...link, anchor: 'history' })));

        select(editor, 9);
        fireEvent.click(linkButton());
        fireEvent.click(within(screen.getByRole('group', { name: 'Link' })).getByRole('button', { name: 'Remove link' }));

        expect(onChange).toHaveBeenLastCalledWith(doc(text('Read our story')));
        expect(linkButton()).toHaveAttribute('aria-pressed', 'false');
    });

    it('should leave the text as it is when the link is cancelled', () => {
        const { onChange, editor } = renderRichText(doc(text('Read our story')));
        select(editor, 6, 15);
        fireEvent.click(linkButton());

        fireEvent.click(within(screen.getByRole('group', { name: 'Link' })).getByRole('button', { name: 'Cancel' }));

        expect(screen.queryByRole('group', { name: 'Link' })).not.toBeInTheDocument();
        expect(onChange).not.toHaveBeenCalled();
    });
});
